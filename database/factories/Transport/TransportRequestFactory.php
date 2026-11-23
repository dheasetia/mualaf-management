<?php

declare(strict_types=1);

namespace Database\Factories\Transport;

use App\Domain\Converts\Models\Convert;
use App\Domain\Transport\Enums\PickupLocationType;
use App\Domain\Transport\Enums\PickupStatus;
use App\Domain\Transport\Models\TransportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<TransportRequest>
 */
final class TransportRequestFactory extends Factory
{
    protected $model = TransportRequest::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'pickup_location_type' => PickupLocationType::Custom,
            'pickup_address' => fake()->streetAddress(),
            'pickup_at' => now()->addDay(),
            'status' => PickupStatus::Requested,
            'requested_by' => User::factory(),
        ];
    }
}
