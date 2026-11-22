<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Services;

use App\Domain\Examinations\Enums\ExamType;
use App\Domain\Examinations\Enums\ResultStatus;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Examinations\Models\ExamResult;

/**
 * Exam outcomes for other modules (certificates), by enrollment id.
 */
final class ExamOutcome
{
    /** Best passed final-exam score in a finalized (or amended) result, or null. */
    public function passedFinal(int $enrollmentId): ?string
    {
        $score = ExamResult::query()
            ->whereIn('status', [ResultStatus::Finalized, ResultStatus::Amended])
            ->where('passed', true)
            ->whereIn('exam_attempt_id', ExamAttempt::query()
                ->where('enrollment_id', $enrollmentId)
                ->whereHas('exam', fn ($query) => $query->where('exam_type', ExamType::Final))
                ->select('id'))
            ->max('final_score');

        return $score === null ? null : (string) $score;
    }
}
