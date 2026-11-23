<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Actions;

use App\Domain\Guidance\Enums\AttendanceStatus;
use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Enums\ProgressSource;
use App\Domain\Guidance\Enums\ProgressStatus;
use App\Domain\Guidance\Enums\SessionStatus;
use App\Domain\Guidance\Exceptions\GuidanceRuleViolation;
use App\Domain\Guidance\Models\Attendance;
use App\Domain\Guidance\Models\ClassSession;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceLesson;
use App\Domain\Guidance\Models\LearningProgress;
use App\Domain\Guidance\Services\AttendanceCalculator;
use App\Domain\Guidance\Services\TeacherAssignments;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * What happens in a class: sessions, attendance and lesson progress.
 * Attendance and progress are recorded only by a teacher currently assigned
 * to that class (critical test #6); sessions by the coordinator
 * (guidance.classes.manage) or an assigned teacher.
 */
final readonly class ClassRecords
{
    public function __construct(private TeacherAssignments $teachers, private AttendanceCalculator $calculator) {}

    /** @param  array{starts_at: string, ends_at: string, lesson_id?: int|null, teacher_user_id?: int|null, notes?: string|null}  $data */
    public function scheduleSession(GuidanceClass $class, array $data, User $actor): ClassSession
    {
        $manages = $actor->hasPermission('guidance.classes.manage');

        if (! $manages && ! $this->teachers->teaches($actor, $class->id)) {
            throw GuidanceRuleViolation::notYourClass();
        }

        $teacherId = $manages ? (int) ($data['teacher_user_id'] ?? 0) : $actor->id;

        if (! $this->teachers->teaches(User::query()->findOrFail($teacherId), $class->id)) {
            throw GuidanceRuleViolation::notYourClass();
        }

        $lessonId = $data['lesson_id'] ?? null;

        if ($lessonId !== null) {
            $this->ensureLessonInProgram((int) $lessonId, $class->program_id);
        }

        $session = new ClassSession;
        $session->class_id = $class->id;
        $session->lesson_id = $lessonId === null ? null : (int) $lessonId;
        $session->teacher_user_id = $teacherId;
        $session->starts_at = CarbonImmutable::parse($data['starts_at']);
        $session->ends_at = CarbonImmutable::parse($data['ends_at']);
        $session->status = SessionStatus::Scheduled;
        $session->notes = $data['notes'] ?? null;
        $session->save();

        return $session;
    }

    public function cancelSession(ClassSession $session, User $actor): void
    {
        $this->ensureTeacherOrManager($actor, $session->class_id);

        if (Attendance::query()->where('class_session_id', $session->id)->exists()) {
            throw GuidanceRuleViolation::invalidTransition($session->status->label(), SessionStatus::Cancelled->label());
        }

        $session->status = SessionStatus::Cancelled;
        $session->save();
    }

    /**
     * Records (or corrects) attendance for a session; the session becomes held
     * and the first attendance activates an enrolled enrollment.
     *
     * @param  array<int, AttendanceStatus>  $statuses  enrollment_id => status
     */
    public function recordAttendance(ClassSession $session, array $statuses, User $actor): void
    {
        $this->ensureTeacher($actor, $session->class_id, 'guidance.attendance.record');

        if ($session->status === SessionStatus::Cancelled) {
            throw GuidanceRuleViolation::sessionCancelled();
        }

        DB::transaction(function () use ($session, $statuses, $actor): void {
            $enrollments = Enrollment::query()
                ->where('class_id', $session->class_id)
                ->whereKey(array_keys($statuses))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($enrollments->count() !== count($statuses)) {
                throw GuidanceRuleViolation::enrollmentNotInClass();
            }

            $session->status = SessionStatus::Held;
            $session->save();

            foreach ($statuses as $enrollmentId => $status) {
                $attendance = Attendance::query()->where('class_session_id', $session->id)->where('enrollment_id', $enrollmentId)->first() ?? new Attendance;
                $attendance->class_session_id = $session->id;
                $attendance->enrollment_id = $enrollmentId;
                $attendance->status = $status;
                $attendance->recorded_by = $actor->id;
                $attendance->recorded_at = CarbonImmutable::now();
                $attendance->save();

                $enrollment = $enrollments->get($enrollmentId);

                if (! $enrollment instanceof Enrollment) {
                    throw GuidanceRuleViolation::enrollmentNotInClass();
                }

                if ($enrollment->status === EnrollmentStatus::Enrolled && in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true)) {
                    $enrollment->status = EnrollmentStatus::Active;
                }

                $this->calculator->refresh($enrollment);
            }
        });
    }

    /** @param  array{progress_status: ProgressStatus|string, score?: float|string|null, competency_notes?: string|null, needs_mentoring?: bool, notes?: string|null}  $data */
    public function recordProgress(Enrollment $enrollment, int $lessonId, array $data, User $actor): LearningProgress
    {
        if ($enrollment->class_id === null) {
            throw GuidanceRuleViolation::notYourClass();
        }

        $this->ensureTeacher($actor, $enrollment->class_id, 'guidance.progress.record');
        $this->ensureLessonInProgram($lessonId, $enrollment->program_id);

        $progress = LearningProgress::query()->where('enrollment_id', $enrollment->id)->where('lesson_id', $lessonId)->first() ?? new LearningProgress;
        $progress->enrollment_id = $enrollment->id;
        $progress->lesson_id = $lessonId;
        $progress->progress_status = $data['progress_status'] instanceof ProgressStatus ? $data['progress_status'] : ProgressStatus::from($data['progress_status']);
        $progress->score = blank($data['score'] ?? null) ? null : (string) $data['score'];
        $progress->competency_notes = $data['competency_notes'] ?? null;
        $progress->needs_mentoring = (bool) ($data['needs_mentoring'] ?? false);
        $progress->notes = $data['notes'] ?? null;
        $progress->assessed_by = $actor->id;
        $progress->assessed_at = CarbonImmutable::now();
        $progress->source = ProgressSource::Direct;
        $progress->save();

        return $progress;
    }

    private function ensureTeacher(User $actor, int $classId, string $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new AuthorizationException;
        }

        if (! $this->teachers->teaches($actor, $classId)) {
            throw GuidanceRuleViolation::notYourClass();
        }
    }

    private function ensureTeacherOrManager(User $actor, int $classId): void
    {
        if (! $actor->hasPermission('guidance.classes.manage') && ! $this->teachers->teaches($actor, $classId)) {
            throw GuidanceRuleViolation::notYourClass();
        }
    }

    private function ensureLessonInProgram(int $lessonId, int $programId): void
    {
        $inProgram = GuidanceLesson::query()
            ->whereKey($lessonId)
            ->whereHas('module', fn ($query) => $query->where('program_id', $programId))
            ->exists();

        if (! $inProgram) {
            throw GuidanceRuleViolation::lessonNotInProgram();
        }
    }
}
