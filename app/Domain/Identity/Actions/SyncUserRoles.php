<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Brings a user's roles in one scope (an office, or the platform when $tenant
 * is null) to the given set: assigns the missing ones and revokes the rest.
 * AssignRole and RevokeRole check the actor's right and audit each change.
 */
final readonly class SyncUserRoles
{
    public function __construct(private AssignRole $assignRole, private RevokeRole $revokeRole) {}

    /**
     * @param  list<int>  $roleIds
     */
    public function handle(User $user, ?Tenant $tenant, array $roleIds, ?string $reason, User $changedBy): void
    {
        if ($user->is($changedBy)) {
            throw IdentityRuleViolation::ownAccount();
        }

        $scope = $tenant === null ? RoleScope::Platform : RoleScope::Tenant;
        $wanted = Role::query()->whereKey($roleIds)->get();

        if ($wanted->count() !== count(array_unique($roleIds)) || $wanted->contains(fn (Role $role): bool => $role->scope_type !== $scope)) {
            throw IdentityRuleViolation::roleScopeMismatch();
        }

        $current = UserRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant?->id)
            ->whereNull('revoked_at')
            ->get();

        $toRevoke = $current->reject(fn (UserRoleAssignment $assignment): bool => $wanted->contains('id', $assignment->role_id));
        $toAssign = $wanted->reject(fn (Role $role): bool => $current->contains('role_id', $role->id));

        if ($toRevoke->isNotEmpty() && blank($reason)) {
            throw IdentityRuleViolation::revokeReasonRequired();
        }

        DB::transaction(function () use ($user, $tenant, $reason, $changedBy, $toRevoke, $toAssign): void {
            $toRevoke->each(fn (UserRoleAssignment $assignment): UserRoleAssignment => $this->revokeRole->handle($assignment, $changedBy, (string) $reason));
            $toAssign->each(fn (Role $role): UserRoleAssignment => $this->assignRole->handle($user, $role, $tenant, $changedBy));
        });
    }
}
