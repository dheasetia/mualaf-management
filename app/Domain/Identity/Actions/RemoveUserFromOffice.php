<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Domain\Tenancy\Actions\EndTenantMembership;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ends a user's work in one office: revokes every role held there and ends the
 * membership. The account itself stays, with its other offices and roles.
 */
final readonly class RemoveUserFromOffice
{
    public function __construct(
        private RevokeRole $revokeRole,
        private EndTenantMembership $endMembership,
        private AuditLogger $audit,
    ) {}

    public function handle(User $user, Tenant $tenant, string $reason, User $removedBy): User
    {
        if ($user->is($removedBy)) {
            throw IdentityRuleViolation::ownAccount();
        }

        if (blank($reason)) {
            throw IdentityRuleViolation::revokeReasonRequired();
        }

        $membership = $user->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->where('status', MembershipStatus::Active)
            ->first();

        if ($membership === null) {
            throw IdentityRuleViolation::notInOffice();
        }

        DB::transaction(function () use ($user, $tenant, $reason, $removedBy, $membership): void {
            UserRoleAssignment::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenant->id)
                ->whereNull('revoked_at')
                ->get()
                ->each(fn (UserRoleAssignment $assignment): UserRoleAssignment => $this->revokeRole->handle($assignment, $removedBy, $reason));

            $this->endMembership->handle($membership);
            $this->audit->record('users.office_removed', $user, newValues: ['tenant' => $tenant->code, 'reason' => $reason], tenantId: $tenant->id, actor: $removedBy);
        });

        return $user;
    }
}
