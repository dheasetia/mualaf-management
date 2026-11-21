<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Exceptions\CannotChangeOwnRoles;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Domain\Identity\Rbac\Authorizer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class RevokeRole
{
    public function __construct(private Authorizer $authorizer) {}

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
            $assignment->revoked_at = CarbonImmutable::now();
            $assignment->revoked_by = $revokedBy->id;
            $assignment->revoke_reason = $reason;
            $assignment->save();
            $this->authorizer->flush();
        }

        return $assignment;
    }
}
