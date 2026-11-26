<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM', 'name_ar' => 'المركز الإسلامي بالدمام', 'name_en' => 'Islamic Center Dammam']);
    $this->jeddah = Tenant::factory()->active()->create(['code' => 'JED', 'name_ar' => 'المركز الإسلامي بجدة', 'name_en' => 'Islamic Center Jeddah']);
});

it('shows the office name instead of a switcher to staff of one office', function (): void {
    $this->actingAs(grantRole(memberOf($this->dammam), 'dai', $this->dammam))
        ->get('/center/DMM')
        ->assertOk()
        ->assertSee('class="office-name"', false)
        ->assertSee('المركز الإسلامي بالدمام')
        ->assertDontSee('fi-tenant-menu-trigger', false);
});

it('keeps the office switcher for staff of several offices', function (): void {
    $user = grantRole(grantRole(memberOf($this->dammam, $this->jeddah), 'dai', $this->dammam), 'dai', $this->jeddah);

    $this->actingAs($user)
        ->get('/center/DMM')
        ->assertOk()
        ->assertSee('fi-tenant-menu-trigger', false)
        ->assertDontSee('class="office-name"', false);
});
