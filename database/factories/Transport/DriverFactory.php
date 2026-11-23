<?php

declare(strict_types=1);

namespace Database\Factories\Transport;

use App\Domain\Transport\Enums\DriverStatus;
use App\Domain\Transport\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<Driver>
 */
final class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone_encrypted' => '+9665'.fake()->numerify('########'),
            'license_expires_on' => now()->addYear()->toDateString(),
            'status' => DriverStatus::Active,
        ];
    }
}
