<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Models\Convert;
use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Enums\GenderPolicy;
use App\Domain\Guidance\Enums\ProgressSource;
use App\Domain\Guidance\Enums\ProgressStatus;
use App\Domain\Guidance\Events\EnrollmentCompleted;
use App\Domain\Guidance\Exceptions\GuidanceRuleViolation;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Guidance\Models\LearningProgress;
use App\Domain\Guidance\Scopes\EnrollmentScope;
use App\Domain\Platform\Enums\Gender;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment of a convert of the office in a program, optionally in a class
 * (09 §9.3, guidance.enroll). Class capacity is checked on the locked class
 * row; one active enrollment per convert and program (generated key).
 */
final readonly class ManageEnrollments
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    public function enroll(int $convertId, int $programId, ?int $classId, User $actor): Enrollment
    {
        $this->authorize($actor);
        $tenantId = $this->tenantContext->requireId();

        $convert = Convert::query()->find($convertId);

        if (! $convert instanceof Convert || $convert->status !== ConvertStatus::Active) {
            throw GuidanceRuleViolation::convertUnavailable();
        }

        $program = GuidanceProgram::query()->where('active', true)->find($programId);

        if (! $program instanceof GuidanceProgram) {
            throw GuidanceRuleViolation::programUnavailable();
        }

        try {
            return DB::transaction(function () use ($convert, $program, $classId, $tenantId, $actor): Enrollment {
                if ($classId !== null) {
                    $this->checkClass($classId, $program->id, $convert->gender);
                }

                $enrollment = new Enrollment;
                $enrollment->convert_id = $convert->id;
                $enrollment->origin_tenant_id = $convert->origin_tenant_id;
                $enrollment->service_tenant_id = $tenantId;
                $enrollment->program_id = $program->id;
                $enrollment->class_id = $classId;
                $enrollment->enrolled_at = CarbonImmutable::now();
                $enrollment->target_completion_date = CarbonImmutable::today()->addDays($program->duration_days);
                $enrollment->status = EnrollmentStatus::Enrolled;
                $enrollment->enrolled_by = $actor->id;
                $enrollment->save();

                $this->continueFromTransfer($enrollment);

                $this->audit->record('guidance.enrolled', $convert, newValues: [
                    'enrollment_id' => $enrollment->id, 'program_id' => $program->id, 'class_id' => $classId,
                ], actor: $actor);

                return $enrollment;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw GuidanceRuleViolation::alreadyEnrolled();
        }
    }

    public function changeStatus(Enrollment $enrollment, EnrollmentStatus $target, User $actor, ?string $reason = null): Enrollment
    {
        $this->authorize($actor);

        if ($target === EnrollmentStatus::Withdrawn && blank($reason)) {
            throw GuidanceRuleViolation::reasonRequired();
        }

        return DB::transaction(function () use ($enrollment, $target, $actor, $reason): Enrollment {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);

            if ($target === EnrollmentStatus::Transferred || ! in_array($target, $locked->status->allowedTransitions(), true)) {
                throw GuidanceRuleViolation::invalidTransition($locked->status->label(), $target->label());
            }

            if ($target === EnrollmentStatus::Completed) {
                $required = $locked->program()->value('minimum_attendance_percentage');

                if ($locked->attendance_percentage !== null && (float) $locked->attendance_percentage < (float) $required) {
                    throw GuidanceRuleViolation::attendanceTooLow((string) $required);
                }

                $locked->completed_at = CarbonImmutable::now();
            }

            if ($target === EnrollmentStatus::Withdrawn) {
                $locked->withdrawn_reason = trim((string) $reason);
            }

            $from = $locked->status;
            $locked->status = $target;
            $locked->save();

            $this->audit->record('guidance.enrollment_status_changed', newValues: [
                'enrollment_id' => $locked->id, 'from' => $from->value, 'to' => $target->value, 'reason' => $reason,
            ], actor: $actor);

            if ($target === EnrollmentStatus::Completed) {
                EnrollmentCompleted::dispatch($locked->id, $locked->convert_id, $locked->service_tenant_id);
            }

            return $locked;
        });
    }

    /**
     * After a convert transfer: the old office's active enrollments become
     * transferred (TransferCompleted listener; runs in the new office's context).
     */
    public function closeForTransfer(int $convertId, int $fromTenantId): int
    {
        return Enrollment::query()
            ->withoutGlobalScope(EnrollmentScope::class)
            ->where('convert_id', $convertId)
            ->where('service_tenant_id', $fromTenantId)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Active, EnrollmentStatus::Paused])
            ->update(['status' => EnrollmentStatus::Transferred, 'updated_at' => CarbonImmutable::now()]);
    }

    private function checkClass(int $classId, int $programId, Gender $gender): void
    {
        $class = GuidanceClass::query()->lockForUpdate()->find($classId);

        if (! $class instanceof GuidanceClass || $class->program_id !== $programId || ! $class->status->acceptsEnrollments()) {
            throw GuidanceRuleViolation::classUnavailable();
        }

        $genderAllowed = match ($class->gender_policy) {
            GenderPolicy::Mixed => true,
            GenderPolicy::Male => $gender === Gender::Male,
            GenderPolicy::Female => $gender === Gender::Female,
        };

        if (! $genderAllowed) {
            throw GuidanceRuleViolation::genderPolicy();
        }

        $taken = Enrollment::query()
            ->where('class_id', $class->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Active, EnrollmentStatus::Paused])
            ->count();

        if ($taken >= $class->capacity) {
            throw GuidanceRuleViolation::classFull();
        }
    }

    /**
     * When the convert's previous enrollment in the same program was closed
     * by a transfer, link it and carry over completed lessons (09 §9.3).
     */
    private function continueFromTransfer(Enrollment $enrollment): void
    {
        $previous = Enrollment::query()
            ->withoutGlobalScope(EnrollmentScope::class)
            ->where('convert_id', $enrollment->convert_id)
            ->where('program_id', $enrollment->program_id)
            ->where('status', EnrollmentStatus::Transferred)
            ->whereNull('transferred_to_enrollment_id')
            ->latest('id')
            ->first();

        if ($previous === null) {
            return;
        }

        $previous->transferred_to_enrollment_id = $enrollment->id;
        $previous->save();

        $completed = LearningProgress::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('enrollment_id', $previous->id)
            ->where('progress_status', ProgressStatus::Completed)
            ->get();

        foreach ($completed as $row) {
            $copy = new LearningProgress;
            $copy->enrollment_id = $enrollment->id;
            $copy->lesson_id = $row->lesson_id;
            $copy->progress_status = ProgressStatus::Completed;
            $copy->score = $row->score;
            $copy->competency_notes = $row->competency_notes;
            $copy->assessed_by = $row->assessed_by;
            $copy->assessed_at = $row->assessed_at;
            $copy->source = ProgressSource::Transferred;
            $copy->save();
        }
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('guidance.enroll')) {
            throw new AuthorizationException;
        }
    }
}
