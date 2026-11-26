<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Enums\VerificationStatus;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Actions\VerifyShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Enums\ShahadahStatus;
use App\Domain\Registration\Events\ShahadahVerified;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Registration\Models\ShahadahWitness;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Pages\RegisterShahadah;
use App\Filament\Center\Resources\ConvertRegistrations\Pages\ManageConvertRegistrations;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->registrar = grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam);
    $this->verifier = grantRole(memberOf($this->dammam), 'shahadah_verifier', $this->dammam);
    $this->dai = inTenant($this->dammam, fn () => Dai::factory()->create());

    $this->register = fn (User $actor, string $iqamah): ConvertRegistration => inTenant($this->dammam, fn () => app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
        profile: new ConvertProfileData('Maria Cruz', Gender::Female, Country::factory()->create()->id),
        identity: new IdentifierData(IdentityType::Iqamah, $iqamah),
        shahadahDate: CarbonImmutable::today(),
        location: 'Center hall',
        primaryDaiId: $this->dai->id,
        witnesses: shahadahWitnesses(),
    ), $actor));
    $this->registration = ($this->register)($this->registrar, saudiId('223344556'));
});

it('verifies registration, shahadah and identity together', function (): void {
    Event::fake([ShahadahVerified::class]);

    inTenant($this->dammam, function (): void {
        app(VerifyShahadah::class)->handle($this->registration, $this->verifier);

        expect($this->registration->refresh())
            ->status->toBe(RegistrationStatus::Verified)
            ->verified_by->toBe($this->verifier->id)
            ->and(ShahadahRecord::query()->sole()->status)->toBe(ShahadahStatus::Verified)
            ->and(ConvertIdentifier::query()->sole()->verification_status)->toBe(VerificationStatus::Verified);

        expectRuleViolation(fn () => app(VerifyShahadah::class)->handle($this->registration, $this->verifier), 'registration');
    });

    expect(AuditLog::query()->where('action', 'shahadah.verified')->sole()->actor_id)->toBe($this->verifier->id);
    Event::assertDispatched(ShahadahVerified::class);
});

it('never lets the person who registered verify it', function (): void {
    $both = grantRole($this->verifier, 'registration_officer', $this->dammam);
    $own = ($this->register)($both, saudiId('245678912'));

    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(VerifyShahadah::class)->handle($own, $both), 'registration'));
});

it('opens the registration page to registration officers only', function (): void {
    $this->actingAs($this->registrar)->get('/center/DMM/register-shahadah')->assertOk();
    $this->actingAs($this->verifier)->get('/center/DMM/register-shahadah')->assertForbidden();
});

it('registers a walk-in from the page', function (): void {
    inCenterPanel($this->dammam, $this->registrar);

    Livewire::test(RegisterShahadah::class)
        ->fillForm([
            'primary_dai_id' => $this->dai->id,
            'identity_type' => 'iqamah',
            'identity_number' => saudiId('276543219'),
            'full_name' => 'Ahmed Reyes',
            'gender' => 'male',
            'nationality_id' => Country::factory()->create()->id,
            'location' => 'Center hall',
            'witnesses' => [
                ['name' => 'Saad Al-Dosari', 'phone' => '0551234011', 'identity_last4' => '1234'],
                ['name' => 'Faisal Al-Shehri', 'phone' => '0551234012', 'relationship' => 'Colleague'],
            ],
        ])
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(inTenant($this->dammam, fn () => ConvertRegistration::query()->count()))->toBe(2)
        ->and(inTenant($this->dammam, fn () => ShahadahWitness::query()->orderBy('id')->pluck('name')->all()))->toContain('Saad Al-Dosari', 'Faisal Al-Shehri');
});

it('does not register from the page without the witnesses', function (): void {
    inCenterPanel($this->dammam, $this->registrar);

    Livewire::test(RegisterShahadah::class)
        ->fillForm([
            'primary_dai_id' => $this->dai->id,
            'identity_type' => 'iqamah',
            'identity_number' => saudiId('276543235'),
            'full_name' => 'No Witness',
            'gender' => 'male',
            'nationality_id' => Country::factory()->create()->id,
            'location' => 'Center hall',
            'witnesses' => [['name' => 'Ahmad Al-Harbi', 'phone' => '0551234001']],
        ])
        ->call('register')
        ->assertHasFormErrors(['witnesses']);

    expect(inTenant($this->dammam, fn () => ConvertRegistration::query()->count()))->toBe(1);
});

it('shows an already registered identity on the identity field', function (): void {
    inCenterPanel($this->dammam, $this->registrar);

    Livewire::test(RegisterShahadah::class)
        ->fillForm([
            'primary_dai_id' => $this->dai->id,
            'identity_type' => 'iqamah',
            'identity_number' => saudiId('223344556'),
            'full_name' => 'Same Person',
            'gender' => 'female',
            'nationality_id' => Country::factory()->create()->id,
            'location' => 'Center hall',
            'witnesses' => [
                ['name' => 'Ahmad Al-Harbi', 'phone' => '0551234001'],
                ['name' => 'Khalid Al-Otaibi', 'phone' => '0551234002'],
            ],
        ])
        ->call('register')
        ->assertHasFormErrors(['identity_number']);
});

it('lets the verifier verify from the queue, not the registrar', function (): void {
    inCenterPanel($this->dammam, $this->registrar);
    Livewire::test(ManageConvertRegistrations::class)
        ->assertActionHidden(TestAction::make('verify')->table($this->registration));

    inCenterPanel($this->dammam, $this->verifier);
    Livewire::test(ManageConvertRegistrations::class)
        ->assertCanSeeTableRecords([$this->registration])
        ->callAction(TestAction::make('verify')->table($this->registration))
        ->assertHasNoActionErrors();

    expect(inTenant($this->dammam, fn () => $this->registration->refresh()->status))->toBe(RegistrationStatus::Verified);
});

it('accepts today\'s shahadah date in Riyadh while UTC is still on the previous day', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 22:30:00', 'UTC'));
    inCenterPanel($this->dammam, $this->registrar);

    Livewire::test(RegisterShahadah::class)
        ->fillForm([
            'primary_dai_id' => $this->dai->id,
            'identity_type' => 'iqamah',
            'identity_number' => saudiId('276543227'),
            'full_name' => 'Late Evening',
            'gender' => 'male',
            'nationality_id' => Country::factory()->create()->id,
            'location' => 'Center hall',
            'shahadah_date' => '2026-11-23',
        ])
        ->call('register')
        ->assertHasNoFormErrors(['shahadah_date']);
});
