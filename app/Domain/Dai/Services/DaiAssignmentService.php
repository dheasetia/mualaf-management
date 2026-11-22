<?php

declare(strict_types=1);

namespace App\Domain\Dai\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dai\Contracts\AssignableSubject;
use App\Domain\Dai\Enums\AssignmentType;
use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of dai_assignments (07-data-model-lifecycle §7.6).
 */
final readonly class DaiAssignmentService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Makes the da'i the subject's primary da'i, ending the previous primary
     * assignment. Assigning the current primary again changes nothing.
     *
     * Lock order (05-database-conventions §5.3): subject, then dai_assignments.
     *
     * @param  Model&AssignableSubject  $subject
     */
    public function assignPrimary(Model $subject, int $daiId, User $actor, ?string $reason = null): DaiAssignment
    {
        return DB::transaction(function () use ($subject, $daiId, $actor, $reason): DaiAssignment {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
            $dai = Dai::query()->find($daiId);

            if (! $dai instanceof Dai || $dai->status !== DaiStatus::Active || $dai->tenant_id !== $subject->responsibleTenantId()) {
                throw DaiRuleViolation::assigneeUnavailable();
            }

            $current = DaiAssignment::query()->active()
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->where('is_primary', true)
                ->lockForUpdate()
                ->first();

            if ($current?->dai_id === $dai->id) {
                return $current;
            }

            $now = CarbonImmutable::now();

            if ($current !== null) {
                $current->ended_at = $now;
                $current->end_reason = $reason ?? 'reassigned';
                $current->save();
            }

            $assignment = new DaiAssignment;
            $assignment->dai_id = $dai->id;
            $assignment->subject_type = $subject->getMorphClass();
            $assignment->subject_id = (int) $subject->getKey();
            $assignment->assignment_type = AssignmentType::Primary;
            $assignment->is_primary = true;
            $assignment->assigned_at = $now;
            $assignment->assigned_by = $actor->id;
            $assignment->save();

            $subject->setPrimaryDai($dai->id);
            $subject->save();

            $this->audit->record(
                'dai.primary_assigned',
                $subject,
                oldValues: $current === null ? [] : ['dai_id' => $current->dai_id],
                newValues: array_filter(['dai_id' => $dai->id, 'reason' => $reason]),
                actor: $actor,
            );

            return $assignment;
        });
    }

    /**
     * Ends the active assignments of one subject, identified by type and id,
     * in every office: on a transfer they belong to the office the person is
     * leaving, while the caller works in the new office's context.
     */
    public function endAllFor(string $subjectType, int $subjectId, string $reason): int
    {
        return DaiAssignment::query()->withoutGlobalScope(TenantScope::class)->active()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->update(['ended_at' => CarbonImmutable::now(), 'end_reason' => $reason, 'updated_at' => CarbonImmutable::now()]);
    }

    /**
     * Whether the da'i holds an active assignment (primary or other) for the subject.
     *
     * @param  Model&AssignableSubject  $subject
     */
    public function isAssigned(Model $subject, int $daiId): bool
    {
        return DaiAssignment::query()->active()
            ->where('dai_id', $daiId)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->exists();
    }

    /**
     * Ids of subjects of one type the da'i holds an active assignment for, as a
     * subquery for visibility scopes ("own" permissions).
     */
    public function assignedSubjectIds(string $subjectType, int $daiId): QueryBuilder
    {
        return DaiAssignment::query()->active()
            ->where('subject_type', $subjectType)
            ->where('dai_id', $daiId)
            ->select('subject_id')
            ->toBase();
    }

    /**
     * Name of the da'i, for subject modules that show who is responsible.
     */
    public function daiName(int $daiId): ?string
    {
        return Dai::query()->whereKey($daiId)->value('full_name');
    }
}
