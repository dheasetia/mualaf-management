<?php

declare(strict_types=1);

use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Region;
use App\Domain\Platform\Models\Religion;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Central\Resources\Cities\Pages\ManageCities;
use App\Filament\Central\Resources\Regions\Pages\ManageRegions;
use App\Filament\Central\Resources\Religions\Pages\ManageReligions;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Filament::setCurrentPanel(Filament::getPanel('central'));
    $this->centralAdmin = grantRole(User::factory()->create(), 'central_admin');
});

it('opens every master data screen for a central admin', function (string $path): void {
    $this->actingAs($this->centralAdmin)->get("/central/{$path}")->assertOk();
})->with(['regions', 'cities', 'countries', 'languages', 'religions', 'budget-categories', 'dai-activity-types']);

it('lets platform users without master.manage read but not change master data', function (): void {
    $auditor = grantRole(User::factory()->create(), 'central_auditor');
    $region = Region::factory()->create();

    $this->actingAs($auditor)->get('/central/regions')->assertOk()->assertSee($region->name_ar);

    Livewire::actingAs($auditor)->test(ManageRegions::class)
        ->assertActionHidden('create')
        ->assertActionHidden(TestAction::make('edit')->table($region));
});

it('keeps office users out of master data screens', function (): void {
    $tenant = Tenant::factory()->active()->create();

    $this->actingAs(grantRole(memberOf($tenant), 'center_admin', $tenant))
        ->get('/central/regions')
        ->assertForbidden();
});

it('stores codes in their canonical case', function (): void {
    Livewire::actingAs($this->centralAdmin)->test(ManageRegions::class)
        ->callAction('create', ['code' => 'qs2', 'name_ar' => 'منطقة', 'name_en' => 'Region', 'sort_order' => 1])
        ->assertHasNoFormErrors();

    Livewire::actingAs($this->centralAdmin)->test(ManageReligions::class)
        ->callAction('create', ['code' => 'Folk_Religion', 'name_ar' => 'ديانة', 'name_en' => 'Religion', 'sort_order' => 1, 'active' => true])
        ->assertHasNoFormErrors();

    expect(Region::query()->where('code', 'QS2')->exists())->toBeTrue()
        ->and(Religion::query()->where('code', 'folk_religion')->exists())->toBeTrue();
});

it('rejects a duplicate code in any case, also against a soft-deleted entry', function (): void {
    Region::factory()->create(['code' => 'XR'])->delete();

    Livewire::actingAs($this->centralAdmin)->test(ManageRegions::class)
        ->callAction('create', ['code' => 'xr', 'name_ar' => 'منطقة', 'name_en' => 'Region', 'sort_order' => 0])
        ->assertHasFormErrors(['code' => 'unique']);
});

it('rejects codes outside the allowed pattern', function (string $code): void {
    Livewire::actingAs($this->centralAdmin)->test(ManageReligions::class)
        ->callAction('create', ['code' => $code, 'name_ar' => 'ديانة', 'name_en' => 'Religion', 'sort_order' => 0])
        ->assertHasFormErrors(['code' => 'regex']);
})->with(['with space', '1starts_with_digit', 'dash-code']);

it('does not delete a region or city that is still in use', function (): void {
    $city = City::factory()->create();
    Tenant::factory()->create(['region_id' => $city->region_id, 'city_id' => $city->id]);

    Livewire::actingAs($this->centralAdmin)->test(ManageRegions::class)
        ->assertActionHidden(TestAction::make('delete')->table($city->region));

    Livewire::actingAs($this->centralAdmin)->test(ManageCities::class)
        ->assertActionHidden(TestAction::make('delete')->table($city));
});

it('soft-deletes an unused city and restores it', function (): void {
    $city = City::factory()->create();

    Livewire::actingAs($this->centralAdmin)->test(ManageCities::class)
        ->callAction(TestAction::make('delete')->table($city));

    expect($city->refresh()->trashed())->toBeTrue();

    Livewire::actingAs($this->centralAdmin)->test(ManageCities::class)
        ->filterTable('trashed', false)
        ->callAction(TestAction::make('restore')->table($city));

    expect($city->refresh()->trashed())->toBeFalse();
});

it('offers deactivation instead of deletion for flag-based master data', function (): void {
    $religion = Religion::factory()->create();

    Livewire::actingAs($this->centralAdmin)->test(ManageReligions::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($religion))
        ->callAction(TestAction::make('edit')->table($religion), ['active' => false])
        ->assertHasNoFormErrors();

    expect($religion->refresh()->active)->toBeFalse();
});
