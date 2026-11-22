<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\CannotChangeOwnRoles;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Domain\Identity\Rbac\Authorizer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RevokeRole
{
    public function __construct(private Authorizer $authorizer, private AuditLogger $audit) {}

    public function handle(UserRoleAssignment $assignment, User $revokedBy, string $reason): UserRoleAssignment
    {
        if ($assignment->user_id === $revokedBy->id) {
            throw CannotChangeOwnRoles::make();
        }

        $allowed = $this->authorizer->hasPlatformPermission($revokedBy, 'users.assign_roles')
            || ($assignment->tenant_id !== null
                && $this->authorizer->hasTenantPermission($revokedBy, $assignment->tenant_id, 'users.assign_roles'));

        if (! $allowed) {
            throw new AuthorizationException('Not allowed to manage roles in this scope.');
        }

        if ($assignment->revoked_at === null) {
            $assignment->loadMissing(['role', 'user']);

            DB::transaction(function () use ($assignment, $revokedBy, $reason): void {
                $assignment->revoked_at = CarbonImmutable::now();
                $assignment->revoked_by = $revokedBy->id;
                $assignment->revoke_reason = $reason;
                $assignment->save();

                $this->audit->record(
                    'roles.revoked',
                    $assignment->user,
                    oldValues: ['role' => $assignment->role->code, 'assignment_id' => $assignment->id],
                    newValues: ['reason' => $reason],
                    tenantId: $assignment->tenant_id,
                    actor: $revokedBy,
                );
            });

            $this->authorizer->flush();
        }

        return $assignment;
    }
}
