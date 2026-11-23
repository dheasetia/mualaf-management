<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContact;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Religion;
use App\Domain\Prospects\Actions\CreateProspect;
use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Enums\ShahadahStatus;
use App\Domain\Registration\Events\ShahadahRecorded;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    seedRbac();
    $this->saudi = Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->philippines = Country::factory()->create();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->registrar = grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam);
    $this->head = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    $this->iqamah = saudiId('212345678');

    [$this->dai, $this->religion] = inTenant($this->dammam, fn (): array => [Dai::factory()->create(), Religion::factory()->create()]);

    $this->readyProspect = fn (): Prospect => inTenant($this->dammam, function (): Prospect {
        $prospect = app(CreateProspect::class)->handle(new ProspectData('Jose Santos', Gender::Male, ProspectSource::DaiContact,
            nationalityId: $this->philippines->id, currentReligionId: $this->religion->id, phone: '0559998877', primaryDaiId: $this->dai->id), $this->head);
        $prospect->forceFill(['status' => ProspectStatus::ReadyForShahadah])->save();

        return $prospect;
    });

    $this->data = fn (array $overrides = []): ShahadahRegistrationData => new ShahadahRegistrationData(...[
        'profile' => new ConvertProfileData('Jose Santos', Gender::Male, $this->philippines->id, islamicName: 'Yusuf'),
        'identity' => new IdentifierData(IdentityType::Iqamah, $this->iqamah),
        'shahadahDate' => CarbonImmutable::today(),
        'location' => 'Islamic Center Dammam',
        ...$overrides,
    ]);

    $this->record = fn (ShahadahRegistrationData $data, ?Tenant $tenant = null, ?User $actor = null): ConvertRegistration => inTenant(
        $tenant ?? $this->dammam,
        fn () => app(RecordShahadah::class)->handle($data, $actor ?? $this->registrar),
    );
});

it('turns a ready prospect into a convert in one registration', function (): void {
    Event::fake([ShahadahRecorded::class]);
    $prospect = ($this->readyProspect)();

    $registration = ($this->record)(($this->data)(['prospectId' => $prospect->id]));

    inTenant($this->dammam, function () use ($registration, $prospect): void {
        $convert = Convert::query()->findOrFail($registration->convert_id);

        expect($registration->registration_no)->toBe('DMM-REG-'.now()->format('Y').'-000001')
            ->and($registration->status)->toBe(RegistrationStatus::PendingVerification)
            ->and(ShahadahRecord::query()->sole()->status)->toBe(ShahadahStatus::Recorded)
            ->and($convert)
            ->origin_tenant_id->toBe($this->dammam->id)
            ->current_tenant_id->toBe($this->dammam->id)
            ->islamic_name->toBe('Yusuf')
            ->previous_religion_id->toBe($this->religion->id)
            ->primary_dai_id->toBe($this->dai->id)
            ->prospect_id->toBe($prospect->id)
            ->and($convert->identifiers()->sole()->is_primary)->toBeTrue()
            ->and(ConvertContact::query()->sole()->value_encrypted)->toBe('+966559998877')
            ->and($convert->statusHistory()->sole()->to_status)->toBe(ConvertStatus::Active);

        $prospect->refresh();
        expect($prospect->status)->toBe(ProspectStatus::Converted)
            ->and($prospect->convert_id)->toBe($convert->id)
            ->and(DaiAssignment::query()->active()->where('subject_type', 'prospect')->exists())->toBeFalse()
            ->and(DaiAssignment::query()->active()->where('subject_type', 'convert')->sole()->dai_id)->toBe($this->dai->id);
    });

    expect(AuditLog::query()->whereIn('action', ['converts.created', 'shahadah.recorded'])->count())->toBe(2);
    Event::assertDispatched(ShahadahRecorded::class);
});

it('blocks a second registration of an identity registered in any office, and keeps the attempt on record', function (): void {
    ($this->record)(($this->data)(['primaryDaiId' => inTenant($this->riyadh, fn () => Dai::factory()->create()->id)]), $this->riyadh,
        grantRole(memberOf($this->riyadh), 'registration_officer', $this->riyadh));
    $prospect = ($this->readyProspect)();

    expectRuleViolation(fn () => ($this->record)(($this->data)(['prospectId' => $prospect->id])), 'identity_number');

    expect(inTenant($this->dammam, fn () => Convert::query()->count()))->toBe(0)
        ->and(inTenant($this->dammam, fn () => $prospect->refresh()->status))->toBe(ProspectStatus::ReadyForShahadah)
        ->and(AuditLog::query()->where('action', 'shahadah.duplicate_blocked')->sole()->actor_id)->toBe($this->registrar->id);
});

it('registers only prospects that are ready for shahadah', function (): void {
    $prospect = ($this->readyProspect)();
    inTenant($this->dammam, fn () => $prospect->forceFill(['status' => ProspectStatus::ConsideringIslam])->save());

    expectRuleViolation(fn () => ($this->record)(($this->data)(['prospectId' => $prospect->id])), 'prospect_id');
});

it('registers a walk-in with a chosen primary da\'i and their contact', function (): void {
    expectRuleViolation(fn () => ($this->record)(($this->data)()), 'primary_dai_id');

    $registration = ($this->record)(($this->data)(['primaryDaiId' => $this->dai->id, 'phone' => '+63 917 000 1111']));

    inTenant($this->dammam, fn () => expect(Convert::query()->findOrFail($registration->convert_id))
        ->prospect_id->toBeNull()
        ->primary_dai_id->toBe($this->dai->id)
        ->and(ConvertContact::query()->sole()->value_encrypted)->toBe('+639170001111'));
});

it('numbers registrations per office and year without gaps, even after a failed attempt', function (): void {
    expect(fn () => ($this->record)(($this->data)(['primaryDaiId' => $this->dai->id, 'phone' => '12'])))
        ->toThrow(RuleViolation::class);

    $first = ($this->record)(($this->data)(['primaryDaiId' => $this->dai->id]));
    $second = ($this->record)(($this->data)(['primaryDaiId' => $this->dai->id, 'identity' => new IdentifierData(IdentityType::Iqamah, saudiId('298765432')),
        'profile' => new ConvertProfileData('Maria Cruz', Gender::Female, $this->philippines->id)]));
    $riyadh = ($this->record)(($this->data)([
        'primaryDaiId' => inTenant($this->riyadh, fn () => Dai::factory()->create()->id),
        'identity' => new IdentifierData(IdentityType::Iqamah, saudiId('287654321')),
        'profile' => new ConvertProfileData('Ravi Kumar', Gender::Male, $this->philippines->id),
    ]), $this->riyadh, grantRole(memberOf($this->riyadh), 'registration_officer', $this->riyadh));

    $year = now()->format('Y');
    expect([$first->registration_no, $second->registration_no, $riyadh->registration_no])
        ->toBe(["DMM-REG-{$year}-000001", "DMM-REG-{$year}-000002", "RUH-REG-{$year}-000001"]);
});

it('does not accept a shahadah date in the future', function (): void {
    expectRuleViolation(fn () => ($this->record)(($this->data)(['primaryDaiId' => $this->dai->id, 'shahadahDate' => CarbonImmutable::now()->addDays(2)])), 'shahadah_date');
});
