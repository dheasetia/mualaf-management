<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Domain\Identity\Models\Role;
use App\Domain\Tenancy\Actions\GrantTenantMembership;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Makes an existing account staff of an office and gives it office roles there.
 */
final readonly class AddUserToOffice
{
    public function __construct(
        private GrantTenantMembership $grantMembership,
        private AssignRole $assignRole,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  iterable<Role>  $roles
     */
    public function handle(User $user, Tenant $tenant, iterable $roles, User $addedBy): User
    {
        if ($user->is($addedBy)) {
            throw IdentityRuleViolation::ownAccount();
        }

        if ($user->tenantMemberships()->where('tenant_id', $tenant->id)->where('status', MembershipStatus::Active)->exists()) {
            throw IdentityRuleViolation::alreadyInOffice();
        }

        DB::transaction(function () use ($user, $tenant, $roles, $addedBy): void {
            $this->grantMembership->handle($user, $tenant);
            $this->audit->record('users.office_added', $user, newValues: ['tenant' => $tenant->code], tenantId: $tenant->id, actor: $addedBy);

            foreach ($roles as $role) {
                $this->assignRole->handle($user, $role, $tenant, $addedBy);
            }
        });

        return $user;
    }
}
