<?php

declare(strict_types=1);

namespace Database\Factories\Transport;

use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Transport\Enums\ActivityTransportStatus;
use App\Domain\Transport\Enums\TransportType;
use App\Domain\Transport\Models\ActivityTransportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ActivityTransportRequest>
 */
final class ActivityTransportRequestFactory extends Factory
{
    protected $model = ActivityTransportRequest::class;

    public function definition(): array
    {
        return [
            'activity_plan_id' => DawahActivityPlan::factory(),
            'requested_by' => User::factory(),
            'transport_type' => TransportType::OfficeVehicle,
            'passenger_count' => 5,
            'departure_at' => now()->addWeek(),
            'status' => ActivityTransportStatus::Requested,
        ];
    }
}
