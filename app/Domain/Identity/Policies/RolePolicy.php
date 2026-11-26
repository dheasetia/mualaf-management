<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Roles are viewed in both panels (offices see office roles only). Custom roles
 * are created, changed and deleted on the platform by holders of
 * users.assign_roles; system roles are read-only (RoleCatalog).
 */
final readonly class RolePolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user) && (! $this->tenantContext->has() || $role->scope_type === RoleScope::Tenant);
    }

    public function create(User $user): bool
    {
        return ! $this->tenantContext->has() && $user->hasPermission('users.assign_roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->create($user) && ! $role->is_system;
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->update($user, $role) && ! UserRoleAssignment::query()->where('role_id', $role->id)->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
