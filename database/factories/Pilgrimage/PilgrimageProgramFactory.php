<?php

declare(strict_types=1);

namespace Database\Factories\Pilgrimage;

use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Enums\ProgramType;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only: an open umrah program without eligibility rules. Use inside inTenant().
 *
 * @extends Factory<PilgrimageProgram>
 */
final class PilgrimageProgramFactory extends Factory
{
    protected $model = PilgrimageProgram::class;

    public function definition(): array
    {
        return [
            'program_type' => ProgramType::Umrah,
            'name' => 'Umrah '.fake()->year(),
            'season_year' => (int) now()->format('Y'),
            'starts_on' => now()->addMonths(2)->toDateString(),
            'ends_on' => now()->addMonths(2)->addDays(10)->toDateString(),
            'registration_deadline' => now()->addMonth()->toDateString(),
            'capacity' => 40,
            'eligibility_rules' => [],
            'status' => ProgramStatus::Open,
            'created_by' => User::factory(),
        ];
    }
}
