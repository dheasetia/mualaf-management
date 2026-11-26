<?php

declare(strict_types=1);

use App\Domain\International\Models\ConsentRecord;
use App\Domain\International\Models\ExternalIslamicOrganization;
use App\Domain\International\Models\InternationalReferral;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Converts\Pages\ViewConvert;
use App\Filament\Center\Resources\Converts\RelationManagers\ConsentsRelationManager;
use App\Filament\Center\Resources\Referrals\Pages\ManageReferrals;
use App\Filament\Central\Resources\ExternalOrganizations\Pages\ManageExternalOrganizations;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->head = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->convert = documentsConvert($this->tenant);
});

it('records a consent and creates a referral from the panel', function (): void {
    inCenterPanel($this->tenant, $this->head);
    $country = Country::factory()->create();

    Livewire::test(ConsentsRelationManager::class, ['ownerRecord' => $this->convert, 'pageClass' => ViewConvert::class])
        ->callAction(TestAction::make('recordConsent')->table(), ['consent_type' => 'international_sharing', 'fields' => ['name'], 'method' => 'written'])
        ->assertHasNoActionErrors();

    Livewire::test(ManageReferrals::class)
        ->callAction(TestAction::make('createReferral')->table(), [
            'convert_id' => $this->convert->id, 'destination_country_id' => $country->id, 'referral_reason' => 'Going home', 'information_scope' => ['name'],
        ])
        ->assertHasNoActionErrors();

    expect(inTenant($this->tenant, fn () => ConsentRecord::query()->count()))->toBe(1)
        ->and(inTenant($this->tenant, fn () => InternationalReferral::query()->sole()->status->value))->toBe('ready');
});

it('keeps the organization directory in Central for its managers', function (): void {
    $admin = grantRole(User::factory()->create(), 'central_admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('central'));

    Livewire::test(ManageExternalOrganizations::class)
        ->callAction('create', ['organization_name' => 'Davao Islamic Center', 'organization_type' => 'islamic_center', 'country_id' => Country::factory()->create()->id, 'city' => 'Davao'])
        ->assertHasNoActionErrors();

    expect(ExternalIslamicOrganization::query()->sole()->verification_status->value)->toBe('unverified');
    $this->actingAs(grantRole(User::factory()->create(), 'central_auditor'))->get('/central/organizations-abroad')->assertForbidden();
});
