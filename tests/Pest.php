<?php

declare(strict_types=1);

use App\Domain\Converts\Services\IdentityNumber;
use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RbacSeeder;
use Filament\Facades\Filament;
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

function seedRbac(): void
{
    test()->seed(RbacSeeder::class);
}

/**
 * Gives the user a system role (seeded by RbacSeeder) as the system would at bootstrap.
 */
function grantRole(User $user, string $roleCode, ?Tenant $tenant = null): User
{
    app(AssignRole::class)->handle($user, Role::query()->where('code', $roleCode)->sole(), $tenant, null);

    return $user;
}

/**
 * Enables an authenticator app, so panels that require MFA let the user in.
 */
function withAuthenticator(User $user): User
{
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    return $user;
}

/** Livewire tests skip panel middleware: sign in and set what SetTenantContext would. */
function inCenterPanel(Tenant $tenant, User $user): User
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('center'));
    Filament::setTenant($tenant);
    app(TenantContext::class)->set($tenant);

    return $user;
}

/**
 * A valid Saudi ID number (Luhn) from its first nine digits.
 */
function saudiId(string $firstNine): string
{
    foreach (range(0, 9) as $check) {
        if (IdentityNumber::passesLuhn($firstNine.$check)) {
            return $firstNine.$check;
        }
    }

    throw new LogicException('unreachable');
}
