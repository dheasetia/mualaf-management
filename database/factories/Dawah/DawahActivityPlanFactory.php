<?php

declare(strict_types=1);

namespace Database\Factories\Dawah;

use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Platform\Models\DaiActivityType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only (a draft). Use inside inTenant().
 *
 * @extends Factory<DawahActivityPlan>
 */
final class DawahActivityPlanFactory extends Factory
{
    protected $model = DawahActivityPlan::class;

    public function definition(): array
    {
        $dai = Dai::factory();

        return [
            'plan_number' => 'T-PLAN-'.fake()->unique()->numerify('########'),
            'title' => fake()->sentence(4),
            'activity_type_id' => DaiActivityType::factory(),
            'objective' => fake()->sentence(),
            'proposed_by_dai_id' => $dai,
            'responsible_dai_id' => fn (array $attributes): mixed => $attributes['proposed_by_dai_id'],
            'planned_date' => now()->addWeek()->toDateString(),
            'status' => PlanStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
