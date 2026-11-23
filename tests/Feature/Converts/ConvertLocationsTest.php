<?php

declare(strict_types=1);

use App\Domain\Companies\Models\Company;
use App\Domain\Converts\Actions\ChangeConvertStatus;
use App\Domain\Converts\Actions\SaveConvertLocationRecord;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContactPerson;
use App\Domain\Converts\Models\ConvertEmployment;
use App\Domain\Converts\Models\ConvertResidence;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Converts\Pages\ViewConvert;
use App\Filament\Center\Resources\Converts\RelationManagers\ResidencesRelationManager;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->head = withAuthenticator(grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam));
    $this->convert = inTenant($this->dammam, fn () => Convert::factory()->create());
    $this->save = fn (string $type, array $data, $record = null, ?Convert $convert = null) => inTenant($this->dammam,
        fn () => app(SaveConvertLocationRecord::class)->handle($convert ?? $this->convert, $type, $record, $data, $this->head));
});

it('keeps one current employment, with the work phone encrypted', function (): void {
    $company = inTenant($this->dammam, fn () => Company::factory()->create());
    $first = ($this->save)(ConvertEmployment::class, ['company_id' => $company->id, 'employment_status' => 'employed', 'is_current' => true, 'work_phone_encrypted' => '0138000000']);
    $second = ($this->save)(ConvertEmployment::class, ['company_name_free' => 'Small workshop', 'employment_status' => 'employed', 'is_current' => true]);

    inTenant($this->dammam, fn () => expect(ConvertEmployment::query()->where('is_current', true)->sole()->id)->toBe($second->id)
        ->and($first->refresh()->is_current)->toBeFalse());
    expect(DB::table('convert_employments')->where('id', $first->id)->value('work_phone_encrypted'))->not->toContain('0138000000');
});

it('takes residence coordinates from the Google Maps link and keeps one current residence', function (): void {
    $camp = ($this->save)(ConvertResidence::class, ['residence_type' => 'workers_camp', 'is_current' => true, 'is_pickup_point' => true,
        'google_maps_url' => 'https://maps.google.com/?q=26.2172,50.1971']);
    ($this->save)(ConvertResidence::class, ['residence_type' => 'apartment', 'is_current' => true]);

    expect((float) $camp->latitude)->toBe(26.2172)
        ->and(inTenant($this->dammam, fn () => $camp->refresh()->is_current))->toBeFalse();

    expectRuleViolation(fn () => ($this->save)(ConvertResidence::class, ['residence_type' => 'house', 'google_maps_url' => 'https://example.com/x']), 'google_maps_url');
});

it('keeps one primary contact person, and deletes one with an audit entry', function (): void {
    $hr = ($this->save)(ConvertContactPerson::class, ['name' => 'HR Manager', 'contact_role' => 'hr', 'is_primary' => true, 'disclosure_scope' => 'none']);
    $friend = ($this->save)(ConvertContactPerson::class, ['name' => 'Ali', 'contact_role' => 'friend', 'is_primary' => true, 'disclosure_scope' => 'knows_conversion', 'permission_to_contact' => true]);

    inTenant($this->dammam, function () use ($hr, $friend): void {
        expect($hr->refresh()->is_primary)->toBeFalse();
        app(SaveConvertLocationRecord::class)->deleteContactPerson($this->convert, $friend, $this->head);
        expect(ConvertContactPerson::query()->pluck('id')->all())->toBe([$hr->id]);
    });
});

it('refuses changes on closed records and on records of another convert', function (): void {
    $other = inTenant($this->dammam, fn () => Convert::factory()->create());
    $foreign = ($this->save)(ConvertResidence::class, ['residence_type' => 'house'], null, $other);

    expectRuleViolation(fn () => ($this->save)(ConvertResidence::class, ['name' => 'x'], $foreign), 'status');

    inTenant($this->dammam, fn () => app(ChangeConvertStatus::class)->handle($this->convert, ConvertStatus::RecordClosed, $this->head, 'Closed'));
    expectRuleViolation(fn () => ($this->save)(ConvertResidence::class, ['residence_type' => 'house'], null, $this->convert->refresh()), 'status');
});

it('shows locations with the full profile only, read-only without converts.update', function (): void {
    $restricted = inTenant($this->dammam, fn () => Convert::factory()->create(['disclosure_risk_level' => DisclosureLevel::Restricted]));
    $registrar = withAuthenticator(grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam));
    $auditor = withAuthenticator(grantRole(memberOf($this->dammam), 'local_auditor', $this->dammam));

    inCenterPanel($this->dammam, $registrar);
    expect(ResidencesRelationManager::canViewForRecord($restricted, ViewConvert::class))->toBeFalse()
        ->and(ResidencesRelationManager::canViewForRecord($this->convert, ViewConvert::class))->toBeTrue();

    inCenterPanel($this->dammam, $auditor);
    Livewire::test(ResidencesRelationManager::class, ['ownerRecord' => $this->convert, 'pageClass' => ViewConvert::class])
        ->assertActionHidden(TestAction::make('create')->table());

    inCenterPanel($this->dammam, $this->head);
    Livewire::test(ResidencesRelationManager::class, ['ownerRecord' => $this->convert, 'pageClass' => ViewConvert::class])
        ->callAction(TestAction::make('create')->table(), ['residence_type' => 'company_dormitory', 'name' => 'Camp 4', 'is_current' => true])
        ->assertHasNoActionErrors();

    expect(inTenant($this->dammam, fn () => ConvertResidence::query()->sole()->name))->toBe('Camp 4');
});
