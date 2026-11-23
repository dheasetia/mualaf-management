<?php

declare(strict_types=1);

namespace Database\Factories\Examinations;

use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Enums\ExamType;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Guidance\Models\GuidanceProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<Exam>
 */
final class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'program_id' => GuidanceProgram::factory(),
            'title' => 'Exam '.fake()->numberBetween(1, 999),
            'exam_type' => ExamType::Final,
            'maximum_score' => 100,
            'passing_score' => 60,
            'status' => ExamStatus::InProgress,
        ];
    }
}
