<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Exceptions\DawahRuleViolation;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Plan state machine (12 §12.5): the da'i submits; Head of Dawah reviews
 * (dawah.plans.review), returns for revision or rejects with a reason, and
 * approves (dawah.plans.approve, never their own proposal, and only once
 * budget.approve has set every budget line's approved amount); the responsible
 * da'i or an approver schedules, starts (not before the planned day) and
 * completes. Cancelling needs a reason and is possible until it starts.
 * Reports are handled by ReportWorkflow.
 */
final readonly class PlanWorkflow
{
    public function __construct(private DawahActors $actors, private AuditLogger $audit) {}

    public function submit(DawahActivityPlan $plan, User $actor): DawahActivityPlan
    {
        $this->require($actor->hasPermission('dawah.plans.submit') && $this->actors->isPlanDai($actor, $plan));

        return $this->move($plan, PlanStatus::Submitted, $actor, function (DawahActivityPlan $locked): void {
            $locked->submitted_at = CarbonImmutable::now();
            $locked->revision_note = null;
        });
    }

    public function startReview(DawahActivityPlan $plan, User $actor): DawahActivityPlan
    {
        $this->require($actor->hasPermission('dawah.plans.review'));

        return $this->move($plan, PlanStatus::SupervisorReview, $actor, function (DawahActivityPlan $locked) use ($actor): void {
            $locked->supervisor_user_id = $actor->id;
        });
    }

    public function requestRevision(DawahActivityPlan $plan, User $actor, ?string $note): DawahActivityPlan
    {
        $this->require($actor->hasPermission('dawah.plans.review'));
        $note = $this->reason($note);

        return $this->move($plan, PlanStatus::NeedsRevision, $actor, function (DawahActivityPlan $locked) use ($note): void {
            $locked->revision_note = $note;
        }, ['note' => $note]);
    }

    public function reject(DawahActivityPlan $plan, User $actor, ?string $reason): DawahActivityPlan
    {
        $this->require($actor->hasPermission('dawah.plans.review') || $actor->hasPermission('dawah.plans.approve'));
        $reason = $this->reason($reason);

        return $this->move($plan, PlanStatus::Rejected, $actor, function (DawahActivityPlan $locked) use ($reason): void {
            $locked->rejection_reason = $reason;
        }, ['reason' => $reason]);
    }

    public function approve(DawahActivityPlan $plan, User $actor): DawahActivityPlan
    {
        $this->require($actor->hasPermission('dawah.plans.approve'));

        if ($this->actors->isPlanDai($actor, $plan)) {
            throw DawahRuleViolation::selfApproval();
        }

        return $this->move($plan, PlanStatus::Approved, $actor, function (DawahActivityPlan $locked) use ($actor): void {
            if ($locked->budgetLines()->whereNull('approved_amount')->exists()) {
                throw DawahRuleViolation::budgetNotApproved();
            }

            $locked->approved_by = $actor->id;
            $locked->approved_at = CarbonImmutable::now();
            $locked->approved_budget ??= '0.00';
        });
    }

    public function schedule(DawahActivityPlan $plan, User $actor): DawahActivityPlan
    {
        $this->require($this->actors->runsPlan($actor, $plan));

        return $this->move($plan, PlanStatus::Scheduled, $actor);
    }

    public function start(DawahActivityPlan $plan, User $actor): DawahActivityPlan
    {
        $this->require($this->actors->runsPlan($actor, $plan));

        $today = CarbonImmutable::now()->setTimezone((string) config('app.display_timezone'))->toDateString();

        if ($plan->planned_date->toDateString() > $today) {
            throw DawahRuleViolation::tooEarlyToStart();
        }

        return $this->move($plan, PlanStatus::InProgress, $actor);
    }

    public function complete(DawahActivityPlan $plan, User $actor): DawahActivityPlan
    {
        $this->require($this->actors->runsPlan($actor, $plan));

        return $this->move($plan, PlanStatus::Completed, $actor);
    }

    public function cancel(DawahActivityPlan $plan, User $actor, ?string $reason): DawahActivityPlan
    {
        $beforeReview = in_array($plan->status, [PlanStatus::Draft, PlanStatus::Submitted, PlanStatus::NeedsRevision], true);
        $this->require($actor->hasPermission('dawah.plans.approve')
            || ($beforeReview && $actor->hasPermission('dawah.plans.submit') && $this->actors->isPlanDai($actor, $plan)));
        $reason = $this->reason($reason);

        return $this->move($plan, PlanStatus::Cancelled, $actor, function (DawahActivityPlan $locked) use ($reason): void {
            $locked->cancellation_reason = $reason;
        }, ['reason' => $reason]);
    }

    /**
     * Locks the plan, checks the transition on the locked row, applies the
     * change and audits it.
     *
     * @param  (Closure(DawahActivityPlan): void)|null  $apply
     * @param  array<string, mixed>  $details
     */
    public function move(DawahActivityPlan $plan, PlanStatus $target, User $actor, ?Closure $apply = null, array $details = []): DawahActivityPlan
    {
        return DB::transaction(function () use ($plan, $target, $actor, $apply, $details): DawahActivityPlan {
            $locked = DawahActivityPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw DawahRuleViolation::invalidTransition($from->label(), $target->label());
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->status = $target;
            $locked->save();

            $this->audit->record('dawah_plan.status_changed', $locked, ['status' => $from->value], ['status' => $target->value, ...$details], actor: $actor);

            return $locked;
        });
    }

    private function reason(?string $reason): string
    {
        return blank($reason) ? throw DawahRuleViolation::reasonRequired() : trim((string) $reason);
    }

    private function require(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
