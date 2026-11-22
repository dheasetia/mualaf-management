<?php

declare(strict_types=1);

namespace Database\Factories\Examinations;

use App\Domain\Examinations\Enums\ResultStatus;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Examinations\Models\ExamResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ExamResult>
 */
final class ExamResultFactory extends Factory
{
    protected $model = ExamResult::class;

    public function definition(): array
    {
        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'final_score' => 75,
            'passed' => true,
            'status' => ResultStatus::Provisional,
        ];
    }
}
