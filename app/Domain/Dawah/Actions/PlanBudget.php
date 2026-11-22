<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Exceptions\DawahRuleViolation;
use App\Domain\Dawah\Models\DawahActivityBudget;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\BudgetVariance;
use App\Domain\Dawah\Services\DawahActors;
use App\Domain\Platform\Models\BudgetCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Budget lines of a plan (08 §8.3):
 * - the plan's da'i writes estimates (quantity × unit cost) while the plan
 *   is editable; they are frozen from then on;
 * - budget.approve sets approved amounts while the plan is under review,
 *   never for a plan of their own; the plan can only be approved once
 *   every line has one, and the total is copied to the plan;
 * - actuals are recorded by budget.record_actual or by the responsible
 *   da'i while reporting, from the start of the activity until the report
 *   is submitted; a variance beyond the threshold needs a note.
 */
final readonly class PlanBudget
{
    private const array APPROVAL_OPEN = [PlanStatus::Submitted, PlanStatus::SupervisorReview];

    private const array ACTUALS_OPEN = [PlanStatus::InProgress, PlanStatus::Completed];

    public function __construct(private DawahActors $actors, private AuditLogger $audit) {}

    /** @param  array{budget_category_id: int|string, description: string, quantity: string|int|float, unit_cost: string|int|float, notes?: string|null}  $data */
    public function saveLine(DawahActivityPlan $plan, ?DawahActivityBudget $line, array $data, User $actor): DawahActivityBudget
    {
        $plan = $this->freshPlan($plan);
        $this->requirePlanDai($plan, $actor);

        if (! $plan->status->isEditable()) {
            throw DawahRuleViolation::budgetClosed();
        }

        if (! BudgetCategory::query()->whereKey((int) $data['budget_category_id'])->where('active', true)->exists()) {
            throw DawahRuleViolation::categoryUnavailable();
        }

        $quantity = BudgetVariance::cents((string) $data['quantity']);
        $unitCost = BudgetVariance::cents((string) $data['unit_cost']);

        if ($quantity <= 0 || $unitCost < 0) {
            throw DawahRuleViolation::invalidAmount();
        }

        return DB::transaction(function () use ($plan, $line, $data, $quantity, $unitCost): DawahActivityBudget {
            $line ??= new DawahActivityBudget;
            $line->activity_plan_id = $plan->id;
            $line->budget_category_id = (int) $data['budget_category_id'];
            $line->description = (string) $data['description'];
            $line->quantity = BudgetVariance::format($quantity);
            $line->unit_cost = BudgetVariance::format($unitCost);
            $line->estimated_amount = BudgetVariance::format(intdiv($quantity * $unitCost + 50, 100));
            $line->notes = blank($data['notes'] ?? null) ? null : (string) $data['notes'];
            $line->save();

            $this->refreshTotals($plan);

            return $line;
        });
    }

    public function removeLine(DawahActivityBudget $line, User $actor): void
    {
        $plan = $this->freshPlan($line->plan()->firstOrFail());
        $this->requirePlanDai($plan, $actor);

        if (! $plan->status->isEditable()) {
            throw DawahRuleViolation::budgetClosed();
        }

        DB::transaction(function () use ($line, $plan): void {
            $line->delete();
            $this->refreshTotals($plan);
        });
    }

    public function approveLine(DawahActivityBudget $line, string|int|float $amount, User $actor): DawahActivityBudget
    {
        if (! $actor->hasPermission('budget.approve')) {
            throw new AuthorizationException;
        }

        $plan = $this->freshPlan($line->plan()->firstOrFail());

        if ($this->actors->isPlanDai($actor, $plan)) {
            throw DawahRuleViolation::selfApproval();
        }

        if (! in_array($plan->status, self::APPROVAL_OPEN, true)) {
            throw DawahRuleViolation::budgetClosed();
        }

        $cents = BudgetVariance::cents((string) $amount);

        if ($cents < 0) {
            throw DawahRuleViolation::invalidAmount();
        }

        return DB::transaction(function () use ($line, $plan, $cents, $actor): DawahActivityBudget {
            $line->approved_amount = BudgetVariance::format($cents);
            $line->save();
            $this->refreshTotals($plan);

            $this->audit->record('dawah_budget.approved', $plan, newValues: ['line_id' => $line->id, 'approved_amount' => $line->approved_amount], actor: $actor);

            return $line;
        });
    }

    public function recordActual(DawahActivityBudget $line, string|int|float $amount, ?string $note, User $actor): DawahActivityBudget
    {
        $plan = $this->freshPlan($line->plan()->firstOrFail());
        $reporting = $actor->hasPermission('dawah.reports.submit') && $this->actors->isResponsible($actor, $plan);

        if (! $actor->hasPermission('budget.record_actual') && ! $reporting) {
            throw new AuthorizationException;
        }

        if (! in_array($plan->status, self::ACTUALS_OPEN, true)) {
            throw DawahRuleViolation::budgetClosed();
        }

        $cents = BudgetVariance::cents((string) $amount);

        if ($cents < 0) {
            throw DawahRuleViolation::invalidAmount();
        }

        $actual = BudgetVariance::format($cents);
        $note = blank($note) ? $line->notes : trim((string) $note);

        if (BudgetVariance::needsExplanation($line->estimated_amount, $line->approved_amount, $actual) && blank($note)) {
            throw DawahRuleViolation::varianceNoteRequired();
        }

        return DB::transaction(function () use ($line, $plan, $actual, $note, $actor): DawahActivityBudget {
            $line->actual_amount = $actual;
            $line->notes = $note;
            $line->save();
            $this->refreshTotals($plan);

            $this->audit->record('dawah_budget.actual_recorded', $plan, newValues: ['line_id' => $line->id, 'actual_amount' => $actual], actor: $actor);

            return $line;
        });
    }

    /** Whether every line has an approved amount (a plan without lines needs none). */
    public function isApproved(DawahActivityPlan $plan): bool
    {
        return ! $plan->budgetLines()->whereNull('approved_amount')->exists();
    }

    private function refreshTotals(DawahActivityPlan $plan): void
    {
        $lines = $plan->budgetLines()->get(['estimated_amount', 'approved_amount', 'actual_amount']);
        $sum = fn (string $column): int => (int) $lines->sum(fn (DawahActivityBudget $line): int => BudgetVariance::cents((string) ($line->getAttribute($column) ?? '0')));

        $plan->estimated_budget = BudgetVariance::format($sum('estimated_amount'));
        $plan->approved_budget = $lines->contains(fn (DawahActivityBudget $line): bool => $line->approved_amount !== null) ? BudgetVariance::format($sum('approved_amount')) : null;
        $plan->actual_budget = $lines->contains(fn (DawahActivityBudget $line): bool => $line->actual_amount !== null) ? BudgetVariance::format($sum('actual_amount')) : null;
        $plan->save();
    }

    private function freshPlan(DawahActivityPlan $plan): DawahActivityPlan
    {
        return DawahActivityPlan::query()->findOrFail($plan->id);
    }

    private function requirePlanDai(DawahActivityPlan $plan, User $actor): void
    {
        if (! $actor->hasPermission('dawah.plans.create') || ! $this->actors->isPlanDai($actor, $plan)) {
            throw new AuthorizationException;
        }
    }
}
