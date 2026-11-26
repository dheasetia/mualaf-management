<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Pages\OfficeProfile;
use App\Models\User;
use Livewire\Livewire;

it('shows the profile page inside the panel, with the sidebar', function (): void {
    seedRbac();
    $tenant = Tenant::factory()->active()->create(['code' => 'DMM']);

    $dai = grantRole(memberOf($tenant), 'dai', $tenant);

    $this->actingAs($dai)
        ->get('/center/DMM/profile')
        ->assertOk()
        ->assertSee('fi-sidebar-nav', false);

    // The user menu inside the office links there; the office-less page still works.
    $this->actingAs($dai)->get('/center/DMM')->assertSee('/center/DMM/profile', false);
    $this->actingAs($dai)->get('/center/profile')->assertOk();

    $this->actingAs(withAuthenticator(grantRole(User::factory()->create(), 'central_admin')))
        ->get('/central/profile')
        ->assertOk()
        ->assertSee('fi-sidebar-nav', false);
});

it('saves the profile from the office page', function (): void {
    seedRbac();
    $tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $dai = inCenterPanel($tenant, grantRole(memberOf($tenant), 'dai', $tenant));

    Livewire::test(OfficeProfile::class)
        ->fillForm(['name' => 'Renamed Dai'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dai->refresh()->name)->toBe('Renamed Dai');
});
