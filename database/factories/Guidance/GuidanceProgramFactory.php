<?php

declare(strict_types=1);

namespace Database\Factories\Guidance;

use App\Domain\Guidance\Models\GuidanceProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * National unless tenant_id is given.
 *
 * @extends Factory<GuidanceProgram>
 */
final class GuidanceProgramFactory extends Factory
{
    protected $model = GuidanceProgram::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PRG-??##')),
            'name_ar' => 'برنامج '.fake()->unique()->numberBetween(1, 99999),
            'duration_days' => 90,
        ];
    }
}
