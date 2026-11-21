<?php

declare(strict_types=1);

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'TenantIsolation');

/**
 * Runs the callback with the given tenant as context.
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function inTenant(Tenant $tenant, Closure $callback): mixed
{
    return app(TenantContext::class)->run($tenant, $callback);
}

function memberOf(Tenant ...$tenants): User
{
    $user = User::factory()->create();

    foreach ($tenants as $tenant) {
        UserTenantMembership::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => MembershipStatus::Active,
        ]);
    }

    return $user;
}
