<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Examinations\Enums\AttemptStatus;
use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Exceptions\ExamRuleViolation;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Examinations\Models\ExamComponent;
use App\Domain\Examinations\Models\ExamResult;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Examination Officer (exams.manage): exams with weighted components,
 * attempts for eligible enrollments, and the exam's status.
 */
final readonly class ManageExams
{
    public function __construct(private EnrollmentDirectory $enrollments, private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  program_id, class_id, title, exam_type, maximum_score, passing_score, scheduled_at
     * @param  list<array{component_code: string, name: string, max_score: float|string, weight: float|string}>  $components
     */
    public function save(?Exam $exam, array $data, array $components, User $actor): Exam
    {
        $this->authorize($actor);

        if ($exam !== null && $exam->status !== ExamStatus::Scheduled) {
            throw ExamRuleViolation::notEditable();
        }

        $programId = (int) $data['program_id'];
        $classId = blank($data['class_id'] ?? null) ? null : (int) $data['class_id'];

        if (! $this->enrollments->programExists($programId)) {
            throw ExamRuleViolation::programUnavailable();
        }

        if ($classId !== null && $this->enrollments->programOfClass($classId) !== $programId) {
            throw ExamRuleViolation::classNotInProgram();
        }

        if ($components === []) {
            throw ExamRuleViolation::componentsRequired();
        }

        if ((float) $data['passing_score'] > (float) $data['maximum_score']) {
            throw ExamRuleViolation::passingAboveMaximum();
        }

        return DB::transaction(function () use ($exam, $data, $components, $programId, $classId, $actor): Exam {
            $exam ??= new Exam;
            $isNew = ! $exam->exists;
            $exam->program_id = $programId;
            $exam->class_id = $classId;
            $exam->title = (string) $data['title'];
            $exam->exam_type = $data['exam_type'];
            $exam->maximum_score = (string) $data['maximum_score'];
            $exam->passing_score = (string) $data['passing_score'];
            $exam->scheduled_at = blank($data['scheduled_at'] ?? null) ? null : $data['scheduled_at'];
            $exam->status ??= ExamStatus::Scheduled;
            $exam->save();

            ExamComponent::query()->where('exam_id', $exam->id)->delete();

            foreach ($components as $row) {
                $component = new ExamComponent;
                $component->exam_id = $exam->id;
                $component->component_code = $row['component_code'];
                $component->name = $row['name'];
                $component->max_score = (string) $row['max_score'];
                $component->weight = (string) $row['weight'];
                $component->save();
            }

            $this->audit->record($isNew ? 'exams.created' : 'exams.updated', newValues: ['exam_id' => $exam->id, 'title' => $exam->title], actor: $actor);

            return $exam;
        });
    }

    /**
     * Schedules the next attempt for each enrollment: they must be in the
     * exam's program (and class), without an open attempt, and not already
     * passed in a finalized result.
     *
     * @param  list<int>  $enrollmentIds
     * @return list<ExamAttempt>
     */
    public function addAttempts(Exam $exam, array $enrollmentIds, User $actor): array
    {
        $this->authorize($actor);

        if (! in_array($exam->status, [ExamStatus::Scheduled, ExamStatus::InProgress], true)) {
            throw ExamRuleViolation::notEditable();
        }

        return DB::transaction(function () use ($exam, $enrollmentIds): array {
            $created = [];

            foreach (array_unique($enrollmentIds) as $enrollmentId) {
                if ($this->enrollments->classOfEligible($enrollmentId, $exam->program_id, $exam->class_id) === false) {
                    throw ExamRuleViolation::enrollmentNotEligible();
                }

                $previous = ExamAttempt::query()->where('exam_id', $exam->id)->where('enrollment_id', $enrollmentId)->lockForUpdate()->get();

                if ($previous->contains(fn (ExamAttempt $attempt): bool => in_array($attempt->status, [AttemptStatus::Scheduled, AttemptStatus::InProgress, AttemptStatus::Graded], true))) {
                    throw ExamRuleViolation::attemptOpen();
                }

                $passed = ExamResult::query()->whereIn('exam_attempt_id', $previous->pluck('id'))->where('passed', true)->exists();

                if ($passed) {
                    throw ExamRuleViolation::alreadyPassed();
                }

                $attempt = new ExamAttempt;
                $attempt->exam_id = $exam->id;
                $attempt->enrollment_id = $enrollmentId;
                $attempt->attempt_number = $previous->count() + 1;
                $attempt->status = AttemptStatus::Scheduled;
                $attempt->save();
                $created[] = $attempt;
            }

            return $created;
        });
    }

    public function start(Exam $exam, User $actor): Exam
    {
        return $this->move($exam, ExamStatus::InProgress, $actor);
    }

    public function cancel(Exam $exam, User $actor): Exam
    {
        return $this->move($exam, ExamStatus::Cancelled, $actor);
    }

    private function move(Exam $exam, ExamStatus $target, User $actor): Exam
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($exam, $target, $actor): Exam {
            $locked = Exam::query()->lockForUpdate()->findOrFail($exam->id);

            if (! in_array($target, $locked->status->allowedTransitions(), true)) {
                throw ExamRuleViolation::invalidTransition($locked->status->label(), $target->label());
            }

            $locked->status = $target;
            $locked->save();
            $this->audit->record('exams.status_changed', newValues: ['exam_id' => $locked->id, 'status' => $target->value], actor: $actor);

            return $locked;
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('exams.manage')) {
            throw new AuthorizationException;
        }
    }
}
