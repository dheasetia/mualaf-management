<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;

beforeEach(function (): void {
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
});

it('opens the center panel for a tenant the user belongs to', function (): void {
    $this->actingAs(memberOf($this->dammam))
        ->get('/center/DMM')
        ->assertOk();
})->group('tenant-isolation');

it('does not open the center panel for another tenant', function (): void {
    $this->actingAs(memberOf($this->dammam))
        ->get('/center/RUH')
        ->assertNotFound();
})->group('tenant-isolation');

it('denies the center panel to a user without memberships', function (): void {
    $this->actingAs(memberOf())
        ->get('/center/DMM')
        ->assertForbidden();
})->group('tenant-isolation');

it('denies the central panel until platform roles exist', function (): void {
    $this->actingAs(memberOf($this->dammam))
        ->get('/central')
        ->assertForbidden();
})->group('tenant-isolation');
