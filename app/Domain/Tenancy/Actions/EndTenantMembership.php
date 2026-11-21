<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\UserTenantMembership;
use Carbon\CarbonImmutable;

final class EndTenantMembership
{
    public function handle(UserTenantMembership $membership): UserTenantMembership
    {
        $membership->status = MembershipStatus::Ended;
        $membership->left_at = CarbonImmutable::now();
        $membership->save();

        return $membership;
    }
}
