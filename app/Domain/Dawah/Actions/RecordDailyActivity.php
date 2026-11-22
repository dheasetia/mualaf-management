<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Dawah\Enums\ActivityStatus;
use App\Domain\Dawah\Exceptions\DawahRuleViolation;
use App\Domain\Dawah\Models\DaiDailyActivity;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Prospects\Models\Prospect;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Daily activities of a da'i (08 §8.1, dawah.activities.create): recorded for
 * today or earlier, linked only to the da'i's own prospects and converts,
 * edited while draft or rejected. Submitting verifies at once unless the
 * activity type requires verification by Head of Dawah
 * (dawah.activities.verify, never for their own activity).
 */
final readonly class RecordDailyActivity
{
    public function __construct(private DawahActors $actors, private DaiAssignmentService $assignments, private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function save(?DaiDailyActivity $activity, array $data, User $actor): DaiDailyActivity
    {
        $daiId = $this->ownDai($actor);

        if ($activity !== null && ($activity->dai_id !== $daiId || ! $activity->status->isEditable())) {
            throw $activity->dai_id !== $daiId ? new AuthorizationException : DawahRuleViolation::notEditable();
        }

        if (! DaiActivityType::query()->whereKey((int) ($data['activity_type_id'] ?? 0))->where('active', true)->exists()) {
            throw DawahRuleViolation::activityTypeUnavailable();
        }

        $date = CarbonImmutable::parse((string) $data['activity_date']);

        if ($date->toDateString() > CarbonImmutable::now()->setTimezone((string) config('app.display_timezone'))->toDateString()) {
            throw DawahRuleViolation::futureActivity();
        }

        $start = blank($data['started_at'] ?? null) ? null : substr((string) $data['started_at'], 0, 5);
        $end = blank($data['ended_at'] ?? null) ? null : substr((string) $data['ended_at'], 0, 5);

        if ($start !== null && $end !== null && $end <= $start) {
            throw DawahRuleViolation::endBeforeStart();
        }

        $this->checkRelated($data, $daiId);

        $activity ??= new DaiDailyActivity;
        $activity->dai_id = $daiId;
        $activity->activity_date = $date;
        $activity->activity_type_id = (int) $data['activity_type_id'];
        $activity->title = (string) $data['title'];
        $activity->description = blank($data['description'] ?? null) ? null : (string) $data['description'];
        $activity->started_at = $start;
        $activity->ended_at = $end;
        $activity->duration_minutes = $start !== null && $end !== null
            ? (int) CarbonImmutable::parse($start)->diffInMinutes(CarbonImmutable::parse($end))
            : (blank($data['duration_minutes'] ?? null) ? null : (int) $data['duration_minutes']);

        foreach (['related_prospect_id', 'related_convert_id', 'related_activity_plan_id'] as $field) {
            $activity->setAttribute($field, blank($data[$field] ?? null) ? null : (int) $data[$field]);
        }

        $activity->output_quantity = blank($data['output_quantity'] ?? null) ? null : (string) $data['output_quantity'];
        $activity->output_unit = blank($data['output_unit'] ?? null) ? null : (string) $data['output_unit'];
        $activity->status = $activity->exists ? $activity->status : ActivityStatus::Draft;
        $activity->save();

        return $activity;
    }

    public function submit(DaiDailyActivity $activity, User $actor): DaiDailyActivity
    {
        if ($activity->dai_id !== $this->ownDai($actor)) {
            throw new AuthorizationException;
        }

        if (! $activity->status->isEditable()) {
            throw DawahRuleViolation::invalidTransition($activity->status->label(), ActivityStatus::Submitted->label());
        }

        $needsReview = (bool) DaiActivityType::query()->whereKey($activity->activity_type_id)->value('requires_verification');
        $activity->status = $needsReview ? ActivityStatus::Submitted : ActivityStatus::Verified;
        $activity->rejection_note = null;
        $activity->verified_at = $needsReview ? null : CarbonImmutable::now();
        $activity->save();

        return $activity;
    }

    public function verify(DaiDailyActivity $activity, User $actor): DaiDailyActivity
    {
        return $this->decide($activity, $actor, ActivityStatus::Verified, null);
    }

    public function reject(DaiDailyActivity $activity, User $actor, ?string $note): DaiDailyActivity
    {
        return $this->decide($activity, $actor, ActivityStatus::Rejected, blank($note) ? throw DawahRuleViolation::reasonRequired() : trim((string) $note));
    }

    private function decide(DaiDailyActivity $activity, User $actor, ActivityStatus $target, ?string $note): DaiDailyActivity
    {
        if (! $actor->hasPermission('dawah.activities.verify')) {
            throw new AuthorizationException;
        }

        if ($activity->dai_id === $this->actors->daiId($actor)) {
            throw DawahRuleViolation::ownActivity();
        }

        return DB::transaction(function () use ($activity, $actor, $target, $note): DaiDailyActivity {
            $locked = DaiDailyActivity::query()->lockForUpdate()->findOrFail($activity->id);

            if ($locked->status !== ActivityStatus::Submitted) {
                throw DawahRuleViolation::invalidTransition($locked->status->label(), $target->label());
            }

            $locked->status = $target;
            $locked->verified_by = $actor->id;
            $locked->verified_at = CarbonImmutable::now();
            $locked->rejection_note = $note;
            $locked->save();

            $this->audit->record('dai_activity.'.$target->value, $locked, newValues: array_filter(['note' => $note]), actor: $actor);

            return $locked;
        });
    }

    /** @param  array<string, mixed>  $data */
    private function checkRelated(array $data, int $daiId): void
    {
        $prospectId = $data['related_prospect_id'] ?? null;
        $convertId = $data['related_convert_id'] ?? null;
        $planId = $data['related_activity_plan_id'] ?? null;

        if (! blank($prospectId)) {
            $prospect = Prospect::query()->find((int) $prospectId);

            if (! $prospect instanceof Prospect || ! $this->assignments->isAssigned($prospect, $daiId)) {
                throw DawahRuleViolation::relatedUnavailable();
            }
        }

        if (! blank($convertId)) {
            $convert = Convert::query()->find((int) $convertId);

            if (! $convert instanceof Convert || ! $this->assignments->isAssigned($convert, $daiId)) {
                throw DawahRuleViolation::relatedUnavailable();
            }
        }

        if (! blank($planId) && ! DawahActivityPlan::query()->whereKey((int) $planId)->exists()) {
            throw DawahRuleViolation::relatedUnavailable();
        }
    }

    private function ownDai(User $actor): int
    {
        if (! $actor->hasPermission('dawah.activities.create')) {
            throw new AuthorizationException;
        }

        return $this->actors->daiId($actor) ?? throw DawahRuleViolation::noDaiProfile();
    }
}
