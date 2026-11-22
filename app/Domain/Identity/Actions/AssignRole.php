<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Exceptions\CannotChangeOwnRoles;
use App\Domain\Identity\Exceptions\InvalidRoleScope;
use App\Domain\Identity\Exceptions\RoleAlreadyAssigned;
use App\Domain\Identity\Exceptions\UserNotMemberOfTenant;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class AssignRole
{
    public function __construct(private Authorizer $authorizer, private AuditLogger $audit) {}

    /**
     * @param  User|null  $assignedBy  null only for system bootstrap (seeder, console command)
     */
    public function handle(User $user, Role $role, ?Tenant $tenant, ?User $assignedBy): UserRoleAssignment
    {
        if ($role->scope_type === RoleScope::Tenant && $tenant === null) {
            throw InvalidRoleScope::tenantRoleNeedsTenant($role->code);
        }

        if ($role->scope_type === RoleScope::Platform && $tenant !== null) {
            throw InvalidRoleScope::platformRoleWithTenant($role->code);
        }

        if ($tenant !== null && ! $user->canAccessTenant($tenant)) {
            throw UserNotMemberOfTenant::make($user->id, $tenant->code);
        }

        if ($assignedBy !== null) {
            $this->ensureMayManageRoles($assignedBy, $user, $tenant);
        }

        $assignment = new UserRoleAssignment;
        $assignment->user_id = $user->id;
        $assignment->role_id = $role->id;
        $assignment->tenant_id = $tenant?->id;
        $assignment->assigned_by = $assignedBy?->id;
        $assignment->assigned_at = CarbonImmutable::now();

        try {
            DB::transaction(function () use ($assignment, $role, $user, $tenant, $assignedBy): void {
                $assignment->save();

                $this->audit->record(
                    'roles.assigned',
                    $user,
                    newValues: ['role' => $role->code, 'assignment_id' => $assignment->id],
                    tenantId: $tenant?->id,
                    actor: $assignedBy,
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw RoleAlreadyAssigned::make($role->code);
        }

        $this->authorizer->flush();

        return $assignment;
    }

    /**
     * Platform roles: platform `users.assign_roles`. Office roles: the same, or
     * `users.assign_roles` held in that office. Nobody changes their own roles.
     */
    private function ensureMayManageRoles(User $actor, User $subject, ?Tenant $tenant): void
    {
        if ($actor->is($subject)) {
            throw CannotChangeOwnRoles::make();
        }

        $allowed = $this->authorizer->hasPlatformPermission($actor, 'users.assign_roles')
            || ($tenant !== null && $this->authorizer->hasTenantPermission($actor, $tenant->id, 'users.assign_roles'));

        if (! $allowed) {
            throw new AuthorizationException('Not allowed to manage roles in this scope.');
        }
    }
}
