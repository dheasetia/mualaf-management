<?php

declare(strict_types=1);

namespace Database\Factories\Transport;

use App\Domain\Transport\Enums\VehicleStatus;
use App\Domain\Transport\Enums\VehicleType;
use App\Domain\Transport\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<Vehicle>
 */
final class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'vehicle_number' => 'V-'.fake()->unique()->numerify('###'),
            'plate_number' => strtoupper(fake()->unique()->bothify('??? ####')),
            'vehicle_type' => VehicleType::Van,
            'capacity' => 7,
            'status' => VehicleStatus::Available,
        ];
    }
}
