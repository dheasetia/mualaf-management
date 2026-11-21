<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserTenantMembership> */
final class UserTenantMembershipFactory extends Factory
{
    protected $model = UserTenantMembership::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory()->active(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipStatus::Ended,
            'left_at' => now(),
        ]);
    }
}
