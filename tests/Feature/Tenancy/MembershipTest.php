<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\EndTenantMembership;
use App\Domain\Tenancy\Actions\GrantTenantMembership;
use App\Domain\Tenancy\Exceptions\MembershipAlreadyActive;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

it('allows only one active membership per user and tenant', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->active()->create();
    $grant = app(GrantTenantMembership::class);

    $grant->handle($user, $tenant);
    $grant->handle($user, $tenant);
})->throws(MembershipAlreadyActive::class);

it('allows a new membership after the previous one ended', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->active()->create();
    $grant = app(GrantTenantMembership::class);

    app(EndTenantMembership::class)->handle($grant->handle($user, $tenant));
    $grant->handle($user, $tenant);

    expect($user->tenantMemberships()->count())->toBe(2);
});

it('lists only active tenants with an active membership', function (): void {
    [$dammam, $riyadh, $jeddah] = Tenant::factory()->active()->count(3)->create();
    $suspended = Tenant::factory()->suspended()->create();
    $user = memberOf($dammam, $riyadh, $suspended);
    app(EndTenantMembership::class)->handle($user->tenantMemberships()->where('tenant_id', $riyadh->id)->sole());

    $tenants = $user->getTenants(Filament::getPanel('center'));

    expect($tenants->pluck('id')->all())->toBe([$dammam->id])
        ->and($user->canAccessTenant($jeddah))->toBeFalse()
        ->and($user->canAccessTenant($suspended))->toBeFalse();
});
