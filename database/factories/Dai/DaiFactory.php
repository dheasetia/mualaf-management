<?php

declare(strict_types=1);

namespace Database\Factories\Dai;

use App\Domain\Dai\Enums\EmploymentStatus;
use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates da'i in the tenant in context (BelongsToTenant): use inside inTenant().
 *
 * @extends Factory<Dai>
 */
final class DaiFactory extends Factory
{
    protected $model = Dai::class;

    public function definition(): array
    {
        return [
            'employee_number' => 'D-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'gender' => Gender::Male,
            'specialties' => [],
            'employment_status' => EmploymentStatus::FullTime,
        ];
    }
}
