<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * User management (03-tenancy-authorization §3.7). In an office (Center) the
 * scope is that office's staff; on the platform (Central) it is every account.
 * Nobody manages their own account here: the profile page is for that.
 */
final readonly class UserPolicy
{
    public function __construct(private Authorizer $authorizer, private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $this->viewAny($user) && $this->inScope($target);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    /**
     * Name, email and password. In an office, only for accounts that work in this
     * office alone and hold no platform role, so an office admin cannot take over
     * a national account or another office's staff.
     */
    public function update(User $user, User $target): bool
    {
        if ($user->is($target) || ! $user->hasPermission('users.update') || ! $this->inScope($target)) {
            return false;
        }

        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            return true;
        }

        return ! $this->authorizer->hasAnyPlatformRole($target)
            && ! $target->tenantMemberships()
                ->where('status', MembershipStatus::Active)
                ->where('tenant_id', '!=', $tenantId)
                ->exists();
    }

    public function assignRoles(User $user, User $target): bool
    {
        return ! $user->is($target) && $user->hasPermission('users.assign_roles') && $this->inScope($target);
    }

    /** Platform only: blocks the account everywhere. */
    public function deactivate(User $user, User $target): bool
    {
        return ! $this->tenantContext->has() && ! $user->is($target) && $user->hasPermission('users.deactivate');
    }

    /** Office only: ends the membership and the roles in this office. */
    public function removeFromOffice(User $user, User $target): bool
    {
        return $this->tenantContext->has() && ! $user->is($target) && $user->hasPermission('users.deactivate') && $this->inScope($target);
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function inScope(User $target): bool
    {
        $tenantId = $this->tenantContext->id();

        return $tenantId === null || $target->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->where('status', MembershipStatus::Active)
            ->exists();
    }
}
