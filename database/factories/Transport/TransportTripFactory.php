<?php

declare(strict_types=1);

namespace Database\Factories\Transport;

use App\Domain\Transport\Enums\TripStatus;
use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\TransportTrip;
use App\Domain\Transport\Models\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<TransportTrip>
 */
final class TransportTripFactory extends Factory
{
    protected $model = TransportTrip::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'departs_at' => now()->addDay(),
            'returns_at' => now()->addDay()->addHours(3),
            'status' => TripStatus::Planned,
            'created_by' => User::factory(),
        ];
    }
}
