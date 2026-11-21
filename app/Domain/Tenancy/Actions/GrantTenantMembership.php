<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Exceptions\MembershipAlreadyActive;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

final class GrantTenantMembership
{
    public function handle(User $user, Tenant $tenant): UserTenantMembership
    {
        $membership = new UserTenantMembership;
        $membership->user_id = $user->id;
        $membership->tenant_id = $tenant->id;
        $membership->status = MembershipStatus::Active;
        $membership->joined_at = CarbonImmutable::now();

        try {
            $membership->save();
        } catch (UniqueConstraintViolationException) {
            throw MembershipAlreadyActive::make($user->id, $tenant->id);
        }

        return $membership;
    }
}
