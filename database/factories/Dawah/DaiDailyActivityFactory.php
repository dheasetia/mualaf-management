<?php

declare(strict_types=1);

namespace Database\Factories\Dawah;

use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Enums\ActivityStatus;
use App\Domain\Dawah\Models\DaiDailyActivity;
use App\Domain\Platform\Models\DaiActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<DaiDailyActivity>
 */
final class DaiDailyActivityFactory extends Factory
{
    protected $model = DaiDailyActivity::class;

    public function definition(): array
    {
        return [
            'dai_id' => Dai::factory(),
            'activity_date' => now()->toDateString(),
            'activity_type_id' => DaiActivityType::factory(),
            'title' => fake()->sentence(3),
            'status' => ActivityStatus::Draft,
        ];
    }
}
