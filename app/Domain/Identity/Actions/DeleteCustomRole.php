<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a custom role that was never assigned. Once assigned, the assignment
 * history refers to it, so it stays (revoke the assignments instead).
 */
final readonly class DeleteCustomRole
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Role $role, User $deletedBy): void
    {
        if ($role->is_system) {
            throw IdentityRuleViolation::systemRoleLocked();
        }

        if (UserRoleAssignment::query()->where('role_id', $role->id)->exists()) {
            throw IdentityRuleViolation::roleInUse();
        }

        DB::transaction(function () use ($role, $deletedBy): void {
            $this->audit->record('roles.deleted', $role, oldValues: ['code' => $role->code, 'scope' => $role->scope_type->value], actor: $deletedBy);
            $role->permissions()->detach();
            $role->delete();
        });
    }
}
