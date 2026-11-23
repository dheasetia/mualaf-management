<?php

declare(strict_types=1);

namespace Database\Factories\Examinations;

use App\Domain\Examinations\Enums\AttemptStatus;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Guidance\Models\ClassSession;
use Database\Factories\Guidance\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ExamAttempt>
 */
final class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'enrollment_id' => fn (): int => AttendanceFactory::enrollmentFor(ClassSession::factory()->create()->id),
            'attempt_number' => 1,
            'status' => AttemptStatus::Scheduled,
        ];
    }
}
