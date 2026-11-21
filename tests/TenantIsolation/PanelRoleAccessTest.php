<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
});

it('opens the central panel to platform roles (MFA set-up first) and nobody else', function (): void {
    $superAdmin = grantRole(User::factory()->create(), 'super_admin');

    $this->actingAs($superAdmin)->get('/central')
        ->assertRedirectContains('/central/multi-factor-authentication/set-up');

    $this->actingAs(grantRole(memberOf($this->dammam), 'center_admin', $this->dammam))
        ->get('/central')
        ->assertForbidden();
})->group('tenant-isolation');

it('sends office users with high-privilege roles to their profile to set up MFA', function (): void {
    $approver = grantRole(memberOf($this->dammam), 'approver', $this->dammam);

    $this->actingAs($approver)->get('/center/DMM')->assertRedirect('/center/profile');
    // The profile page itself stays reachable, so there is no redirect loop.
    $this->actingAs($approver)->get('/center/profile')->assertOk();
})->group('tenant-isolation');

it('lets office users with ordinary roles in without MFA', function (): void {
    $this->actingAs(grantRole(memberOf($this->dammam), 'dai', $this->dammam))
        ->get('/center/DMM')
        ->assertOk();
})->group('tenant-isolation');

it('lets a high-privilege user in once MFA is enabled', function (): void {
    $approver = grantRole(memberOf($this->dammam), 'approver', $this->dammam);
    $approver->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($approver)->get('/center/DMM')->assertOk();
})->group('tenant-isolation');
