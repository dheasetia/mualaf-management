<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Guidance\Enums\ClassStatus;
use App\Domain\Guidance\Enums\TeacherRole;
use App\Domain\Guidance\Exceptions\GuidanceRuleViolation;
use App\Domain\Guidance\Models\ClassSchedule;
use App\Domain\Guidance\Models\ClassTeacherAssignment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Classes of the office (guidance.classes.manage): details and weekly
 * schedule, status, and teacher assignments.
 */
final readonly class ManageClasses
{
    private const array FIELDS = ['code', 'name', 'language_id', 'gender_policy', 'capacity', 'room', 'location', 'starts_on', 'ends_on'];

    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{day_of_week: int|string, start_time: string, end_time: string}>  $schedules
     */
    public function save(?GuidanceClass $class, array $data, array $schedules, User $actor): GuidanceClass
    {
        $this->authorize($actor);

        $program = GuidanceProgram::query()->where('active', true)->find($data['program_id'] ?? $class?->program_id);

        if (! $program instanceof GuidanceProgram) {
            throw GuidanceRuleViolation::programUnavailable();
        }

        return DB::transaction(function () use ($class, $data, $schedules, $program, $actor): GuidanceClass {
            $class ??= new GuidanceClass;
            $isNew = ! $class->exists;
            $class->program_id = $program->id;

            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $class->setAttribute($field, $data[$field] === '' ? null : $data[$field]);
                }
            }

            $class->save();

            ClassSchedule::query()->where('class_id', $class->id)->delete();

            foreach ($schedules as $row) {
                $schedule = new ClassSchedule;
                $schedule->class_id = $class->id;
                $schedule->day_of_week = (int) $row['day_of_week'];
                $schedule->start_time = $row['start_time'];
                $schedule->end_time = $row['end_time'];
                $schedule->save();
            }

            $this->audit->record($isNew ? 'guidance.class_created' : 'guidance.class_updated', newValues: ['class_id' => $class->id, 'code' => $class->code], actor: $actor);

            return $class;
        });
    }

    public function changeStatus(GuidanceClass $class, ClassStatus $target, User $actor): GuidanceClass
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($class, $target, $actor): GuidanceClass {
            $locked = GuidanceClass::query()->lockForUpdate()->findOrFail($class->id);

            if (! in_array($target, $locked->status->allowedTransitions(), true)) {
                throw GuidanceRuleViolation::invalidTransition($locked->status->label(), $target->label());
            }

            $from = $locked->status;
            $locked->status = $target;
            $locked->save();

            $this->audit->record('guidance.class_status_changed', newValues: ['class_id' => $locked->id, 'from' => $from->value, 'to' => $target->value], actor: $actor);

            return $locked;
        });
    }

    public function assignTeacher(GuidanceClass $class, int $userId, TeacherRole $role, User $actor): ClassTeacherAssignment
    {
        $this->authorize($actor);

        $isMember = UserTenantMembership::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $this->tenantContext->requireId())
            ->where('status', MembershipStatus::Active)
            ->exists();

        if (! $isMember) {
            throw GuidanceRuleViolation::notAMember();
        }

        $assignment = new ClassTeacherAssignment;
        $assignment->class_id = $class->id;
        $assignment->user_id = $userId;
        $assignment->role = $role;
        $assignment->assigned_at = CarbonImmutable::now();

        try {
            $assignment->save();
        } catch (UniqueConstraintViolationException) {
            throw GuidanceRuleViolation::teacherAlreadyAssigned();
        }

        $this->audit->record('guidance.teacher_assigned', newValues: ['class_id' => $class->id, 'user_id' => $userId, 'role' => $role->value], actor: $actor);

        return $assignment;
    }

    public function releaseTeacher(ClassTeacherAssignment $assignment, User $actor): void
    {
        $this->authorize($actor);

        if ($assignment->released_at !== null) {
            return;
        }

        $assignment->released_at = CarbonImmutable::now();
        $assignment->save();

        $this->audit->record('guidance.teacher_released', newValues: ['class_id' => $assignment->class_id, 'user_id' => $assignment->user_id], actor: $actor);
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('guidance.classes.manage')) {
            throw new AuthorizationException;
        }
    }
}
