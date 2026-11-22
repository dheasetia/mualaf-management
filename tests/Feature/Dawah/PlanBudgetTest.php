<?php

declare(strict_types=1);

use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Actions\ManagePlans;
use App\Domain\Dawah\Actions\PlanBudget;
use App\Domain\Dawah\Actions\PlanWorkflow;
use App\Domain\Dawah\Actions\ReportWorkflow;
use App\Domain\Dawah\Models\DawahActivityBudget;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Platform\Models\BudgetCategory;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedRbac();
    $this->travelTo(now()->startOfDay()->addHours(10));
    $this->tenant = Tenant::factory()->active()->create();
    $this->category = BudgetCategory::factory()->create();
    $this->daiUser = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    $this->head = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->approver = grantRole(memberOf($this->tenant), 'approver', $this->tenant);
    $this->transport = grantRole(memberOf($this->tenant), 'transportation_officer', $this->tenant);
    inTenant($this->tenant, fn () => Dai::factory()->create(['user_id' => $this->daiUser->id]));

    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->budget = fn (): PlanBudget => app(PlanBudget::class);
    $this->plans = fn (): PlanWorkflow => app(PlanWorkflow::class);
    $this->plan = ($this->in)(fn () => app(ManagePlans::class)->save(null, [
        'title' => 'Iftar gathering', 'activity_type_id' => DaiActivityType::factory()->create()->id,
        'objective' => 'Meet new visitors', 'planned_date' => now()->toDateString(),
    ], $this->daiUser));
    $this->line = fn (string $quantity = '10', string $unitCost = '25.50', ?DawahActivityBudget $line = null) => ($this->in)(fn () => ($this->budget)()->saveLine(
        $this->plan, $line, ['budget_category_id' => $this->category->id, 'description' => 'Meals', 'quantity' => $quantity, 'unit_cost' => $unitCost], $this->daiUser,
    ));
    $this->fresh = fn (): DawahActivityPlan => ($this->in)(fn () => DawahActivityPlan::query()->findOrFail($this->plan->id));
});

it('computes line estimates and keeps the plan total in step', function (): void {
    $meals = ($this->line)();
    ($this->line)('2', '100');
    ($this->line)('12', '25.50', $meals);

    expect($meals->refresh()->estimated_amount)->toBe('306.00')
        ->and(($this->fresh)()->estimated_budget)->toBe('506.00');
});

it('freezes estimates once the plan leaves the da\'i and approves amounts only by budget.approve, never the plan\'s da\'i', function (): void {
    $line = ($this->line)();
    ($this->in)(fn () => ($this->plans)()->submit($this->plan, $this->daiUser));

    expectRuleViolation(fn () => ($this->line)('20', '25.50', $line), 'status');
    ($this->in)(function () use ($line): void {
        expect(fn () => ($this->budget)()->approveLine($line, '200', $this->head))->toThrow(AuthorizationException::class);
        expectRuleViolation(fn () => ($this->budget)()->approveLine($line, '200', grantRole($this->daiUser, 'approver', $this->tenant)), 'status');
        ($this->budget)()->approveLine($line, '200', $this->approver);
    });

    expect(($this->fresh)()->approved_budget)->toBe('200.00');
});

it('approves a plan only once every budget line has an approved amount', function (): void {
    $first = ($this->line)();
    $second = ($this->line)('1', '50');

    ($this->in)(function () use ($first, $second): void {
        ($this->plans)()->submit($this->plan, $this->daiUser);
        ($this->plans)()->startReview($this->plan, $this->head);
        ($this->budget)()->approveLine($first, '255', $this->approver);
        expectRuleViolation(fn () => ($this->plans)()->approve($this->plan, $this->head), 'status');

        ($this->budget)()->approveLine($second, '40', $this->approver);
        ($this->plans)()->approve($this->plan, $this->head);
    });

    expect(($this->fresh)())->approved_budget->toBe('295.00')->status->value->toBe('approved');
});

it('records actuals during the activity, asks for a note beyond the variance threshold, and carries the total to the report', function (): void {
    $line = ($this->line)('1', '100');

    ($this->in)(function () use ($line): void {
        ($this->plans)()->submit($this->plan, $this->daiUser);
        ($this->plans)()->startReview($this->plan, $this->head);
        expectRuleViolation(fn () => ($this->budget)()->recordActual($line, '100', null, $this->transport), 'status');
        ($this->budget)()->approveLine($line, '100', $this->approver);
        ($this->plans)()->approve($this->plan, $this->head);
        ($this->plans)()->schedule($this->plan, $this->daiUser);
        ($this->plans)()->start($this->plan, $this->daiUser);

        ($this->budget)()->recordActual($line, '108', null, $this->transport);
        expectRuleViolation(fn () => ($this->budget)()->recordActual($line, '125', null, $this->daiUser), 'notes');
        ($this->budget)()->recordActual($line, '125', 'More guests than expected', $this->daiUser);
        expect(fn () => ($this->budget)()->recordActual($line, '125', 'x', $this->head))->toThrow(AuthorizationException::class);

        ($this->plans)()->complete($this->plan, $this->daiUser);
        app(ReportWorkflow::class)->save($this->plan, ['summary' => 'Done'], $this->daiUser);
        app(ReportWorkflow::class)->submit($this->plan, $this->daiUser);
    });

    expect($line->refresh())->actual_amount->toBe('125.00')->variance_amount->toBe('25.00')
        ->and(($this->fresh)()->actual_budget)->toBe('125.00')
        ->and(($this->in)(fn () => $this->plan->report()->first()->actual_budget))->toBe('125.00');
});

it('approves a plan without budget lines with a zero budget', function (): void {
    ($this->in)(function (): void {
        ($this->plans)()->submit($this->plan, $this->daiUser);
        ($this->plans)()->startReview($this->plan, $this->head);
        ($this->plans)()->approve($this->plan, $this->head);
    });

    expect(($this->fresh)()->approved_budget)->toBe('0.00');
});
