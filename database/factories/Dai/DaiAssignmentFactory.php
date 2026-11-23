<?php

declare(strict_types=1);

namespace Database\Factories\Dai;

use App\Domain\Dai\Enums\AssignmentType;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only, until DaiAssignmentService exists (step 8). Use inside inTenant().
 *
 * @extends Factory<DaiAssignment>
 */
final class DaiAssignmentFactory extends Factory
{
    protected $model = DaiAssignment::class;

    public function definition(): array
    {
        return [
            'dai_id' => Dai::factory(),
            'subject_type' => 'prospect',
            'subject_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'assignment_type' => AssignmentType::Primary,
            'is_primary' => true,
            'assigned_at' => now(),
            'assigned_by' => User::factory(),
        ];
    }

    public function secondary(): static
    {
        return $this->state(fn (): array => ['assignment_type' => AssignmentType::Secondary, 'is_primary' => false]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['ended_at' => now(), 'end_reason' => 'Reassigned']);
    }
}
