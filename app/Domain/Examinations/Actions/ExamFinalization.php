<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Examinations\Enums\AttemptStatus;
use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Enums\ResultStatus;
use App\Domain\Examinations\Exceptions\ExamRuleViolation;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Examinations\Models\ExamResult;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * A graded exam is submitted (exams.grade) and finalized by the Examination
 * Officer (exams.finalize): results become finalized and immutable, attempts
 * finalized, and each enrollment's final score becomes its best finalized
 * score. Later corrections are amendments with a reason, audited.
 */
final readonly class ExamFinalization
{
    public function __construct(private EnrollmentDirectory $enrollments, private AuditLogger $audit) {}

    public function submit(Exam $exam, User $actor): Exam
    {
        if (! $actor->hasPermission('exams.grade')) {
            throw new AuthorizationException;
        }

        return $this->move($exam, ExamStatus::Graded, ExamStatus::PendingFinalization, $actor, 'exams.submitted');
    }

    /** Sends a submitted exam back for corrections. */
    public function reopen(Exam $exam, User $actor): Exam
    {
        $this->authorize($actor);

        return $this->move($exam, ExamStatus::PendingFinalization, ExamStatus::Graded, $actor, 'exams.reopened');
    }

    public function finalize(Exam $exam, User $actor): Exam
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($exam, $actor): Exam {
            $locked = $this->move($exam, ExamStatus::PendingFinalization, ExamStatus::Finalized, $actor, 'exams.finalized');
            $now = CarbonImmutable::now();
            $attempts = ExamAttempt::query()->where('exam_id', $locked->id)->with('result')->get();

            foreach ($attempts as $attempt) {
                if ($attempt->result !== null) {
                    $attempt->result->status = ResultStatus::Finalized;
                    $attempt->result->finalized_by = $actor->id;
                    $attempt->result->finalized_at = $now;
                    $attempt->result->save();
                }

                if ($attempt->status === AttemptStatus::Graded) {
                    $attempt->status = AttemptStatus::Finalized;
                    $attempt->save();
                }

                $this->refreshFinalScore($attempt->enrollment_id);
            }

            return $locked;
        });
    }

    /**
     * Sets a new final score on a finalized result; component scores stay as graded.
     */
    public function amend(ExamResult $result, float $finalScore, User $actor, ?string $reason): ExamResult
    {
        $this->authorize($actor);

        if (blank($reason)) {
            throw ExamRuleViolation::reasonRequired();
        }

        if ($result->status === ResultStatus::Provisional) {
            throw ExamRuleViolation::notFinalized();
        }

        return DB::transaction(function () use ($result, $finalScore, $actor, $reason): ExamResult {
            $attempt = ExamAttempt::query()->with('exam')->findOrFail($result->exam_attempt_id);
            $maximum = (float) $attempt->exam->maximum_score;

            if ($finalScore < 0 || $finalScore > $maximum) {
                throw ExamRuleViolation::scoreOutOfRange($attempt->exam->title);
            }

            $old = ['final_score' => $result->final_score, 'passed' => $result->passed];
            $result->applyAmendment(number_format($finalScore, 2, '.', ''), $finalScore >= (float) $attempt->exam->passing_score, trim((string) $reason));
            $this->refreshFinalScore($attempt->enrollment_id);

            $this->audit->record('exams.result_amended', oldValues: $old, newValues: [
                'result_id' => $result->id, 'final_score' => $result->final_score, 'passed' => $result->passed, 'reason' => $result->amendment_reason,
            ], actor: $actor);

            return $result;
        });
    }

    private function refreshFinalScore(int $enrollmentId): void
    {
        $best = ExamResult::query()
            ->whereIn('status', [ResultStatus::Finalized, ResultStatus::Amended])
            ->whereIn('exam_attempt_id', ExamAttempt::query()->where('enrollment_id', $enrollmentId)->select('id'))
            ->max('final_score');

        if ($best !== null) {
            $this->enrollments->recordFinalScore($enrollmentId, (string) $best);
        }
    }

    private function move(Exam $exam, ExamStatus $from, ExamStatus $to, User $actor, string $auditAction): Exam
    {
        return DB::transaction(function () use ($exam, $from, $to, $actor, $auditAction): Exam {
            $locked = Exam::query()->lockForUpdate()->findOrFail($exam->id);

            if ($locked->status !== $from) {
                throw ExamRuleViolation::invalidTransition($locked->status->label(), $to->label());
            }

            $locked->status = $to;
            $locked->save();
            $this->audit->record($auditAction, newValues: ['exam_id' => $locked->id], actor: $actor);

            return $locked;
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('exams.finalize')) {
            throw new AuthorizationException;
        }
    }
}
