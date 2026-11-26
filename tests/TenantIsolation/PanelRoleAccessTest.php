<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
});

it('opens the central panel to platform roles and nobody else', function (): void {
    $this->actingAs(grantRole(User::factory()->create(), 'super_admin'))->get('/central')->assertOk();

    $this->actingAs(grantRole(memberOf($this->dammam), 'center_admin', $this->dammam))
        ->get('/central')
        ->assertForbidden();
})->group('tenant-isolation');

it('lets office users in with a password only, whatever their role', function (string $role): void {
    $this->actingAs(grantRole(memberOf($this->dammam), $role, $this->dammam))
        ->get('/center/DMM')
        ->assertOk();
})->with(['dai', 'approver'])->group('tenant-isolation');
