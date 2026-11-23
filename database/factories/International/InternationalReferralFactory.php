<?php

declare(strict_types=1);

namespace Database\Factories\International;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\International\Enums\ReferralStatus;
use App\Domain\International\Models\InternationalReferral;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<InternationalReferral>
 */
final class InternationalReferralFactory extends Factory
{
    protected $model = InternationalReferral::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'origin_tenant_id' => Tenant::factory(),
            'primary_dai_id' => Dai::factory(),
            'destination_country_id' => Country::factory(),
            'referral_reason' => 'Returning home',
            'information_scope' => ['name'],
            'status' => ReferralStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
