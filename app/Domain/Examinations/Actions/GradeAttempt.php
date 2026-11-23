<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Actions;

use App\Domain\Examinations\Enums\AttemptStatus;
use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Enums\ResultStatus;
use App\Domain\Examinations\Exceptions\ExamRuleViolation;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Examinations\Models\ExamComponent;
use App\Domain\Examinations\Models\ExamResult;
use App\Domain\Examinations\Models\ExamScore;
use App\Domain\Examinations\Services\ScoreCalculator;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Domain\Guidance\Services\TeacherAssignments;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Grading (exams.grade) while the exam is in progress or graded: every
 * component scored within its maximum gives a provisional result. Teachers
 * grade only attempts of classes they teach; the Examination Officer any.
 * When no attempt is left to grade, the exam becomes graded.
 */
final readonly class GradeAttempt
{
    public function __construct(private TeacherAssignments $teachers, private EnrollmentDirectory $enrollments) {}

    /**
     * @param  array<int, float|string>  $scores  exam_component_id => score
     */
    public function grade(ExamAttempt $attempt, array $scores, User $actor): ExamResult
    {
        $exam = $this->guard($attempt, $actor);
        $components = ExamComponent::query()->where('exam_id', $exam->id)->get();

        if ($components->pluck('id')->diff(array_keys($scores))->isNotEmpty()) {
            throw ExamRuleViolation::scoresIncomplete();
        }

        foreach ($components as $component) {
            $score = (float) $scores[$component->id];

            if ($score < 0 || $score > (float) $component->max_score) {
                throw ExamRuleViolation::scoreOutOfRange($component->name);
            }
        }

        return DB::transaction(function () use ($attempt, $exam, $components, $scores, $actor): ExamResult {
            foreach ($components as $component) {
                $row = ExamScore::query()->where('exam_attempt_id', $attempt->id)->where('exam_component_id', $component->id)->first() ?? new ExamScore;
                $row->exam_attempt_id = $attempt->id;
                $row->exam_component_id = $component->id;
                $row->score = (string) $scores[$component->id];
                $row->save();
            }

            $weighted = [];

            foreach ($components as $component) {
                $weighted[] = [
                    'score' => (float) $scores[$component->id],
                    'max_score' => (float) $component->max_score,
                    'weight' => (float) $component->weight,
                ];
            }

            $finalScore = ScoreCalculator::finalScore($weighted, (float) $exam->maximum_score);

            $result = ExamResult::query()->where('exam_attempt_id', $attempt->id)->first() ?? new ExamResult;
            $result->exam_attempt_id = $attempt->id;
            $result->final_score = number_format($finalScore, 2, '.', '');
            $result->passed = $finalScore >= (float) $exam->passing_score;
            $result->status = ResultStatus::Provisional;
            $result->save();

            $attempt->status = AttemptStatus::Graded;
            $attempt->examiner_user_id = $actor->id;
            $attempt->attempted_at ??= CarbonImmutable::now();
            $attempt->save();

            $this->completeIfDone($exam);

            return $result;
        });
    }

    public function markAbsent(ExamAttempt $attempt, User $actor): void
    {
        $exam = $this->guard($attempt, $actor);

        DB::transaction(function () use ($attempt, $exam, $actor): void {
            ExamResult::query()->where('exam_attempt_id', $attempt->id)->delete();
            ExamScore::query()->where('exam_attempt_id', $attempt->id)->delete();
            $attempt->status = AttemptStatus::Absent;
            $attempt->examiner_user_id = $actor->id;
            $attempt->save();

            $this->completeIfDone($exam);
        });
    }

    private function guard(ExamAttempt $attempt, User $actor): Exam
    {
        if (! $actor->hasPermission('exams.grade')) {
            throw new AuthorizationException;
        }

        $exam = Exam::query()->findOrFail($attempt->exam_id);

        if (! $exam->status->acceptsGrading() || $attempt->status === AttemptStatus::Finalized) {
            throw ExamRuleViolation::notEditable();
        }

        if (! $actor->hasPermission('exams.manage')) {
            $classId = $this->enrollments->classOf($attempt->enrollment_id);

            if ($classId === null || ! $this->teachers->teaches($actor, $classId)) {
                throw ExamRuleViolation::notYourClass();
            }
        }

        return $exam;
    }

    private function completeIfDone(Exam $exam): void
    {
        $open = ExamAttempt::query()->where('exam_id', $exam->id)
            ->whereIn('status', [AttemptStatus::Scheduled, AttemptStatus::InProgress])
            ->exists();

        if (! $open && $exam->status === ExamStatus::InProgress) {
            $exam->status = ExamStatus::Graded;
            $exam->save();
        }
    }
}
