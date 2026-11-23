<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Actions\ManageParticipants;
use App\Domain\Dawah\Actions\ManagePlans;
use App\Domain\Dawah\Actions\PlanWorkflow;
use App\Domain\Dawah\Actions\ReportWorkflow;
use App\Domain\Dawah\Enums\AttendanceStatus;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Enums\ReportStatus;
use App\Domain\Dawah\Models\ActivityParticipant;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\ParticipantDirectory;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->type = DaiActivityType::factory()->create();
    $this->daiUser = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    $this->head = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->dai = inTenant($this->tenant, fn () => Dai::factory()->create(['user_id' => $this->daiUser->id]));

    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->propose = fn (array $overrides = []) => ($this->in)(fn () => app(ManagePlans::class)->save(null, [
        'title' => 'Open day at the mosque',
        'activity_type_id' => $this->type->id,
        'objective' => 'Introduce Islam to visitors',
        'planned_date' => now()->addWeek()->toDateString(),
        ...$overrides,
    ], $this->daiUser));
    $this->plans = fn (): PlanWorkflow => app(PlanWorkflow::class);
    $this->toApproved = function (DawahActivityPlan $plan): DawahActivityPlan {
        return ($this->in)(function () use ($plan): DawahActivityPlan {
            ($this->plans)()->submit($plan, $this->daiUser);
            ($this->plans)()->startReview($plan, $this->head);

            return ($this->plans)()->approve($plan, $this->head);
        });
    };
});

it('numbers a proposed plan per office and year, with the proposer as the responsible da\'i', function (): void {
    $plan = ($this->propose)();

    expect($plan)
        ->plan_number->toBe('DMM-PLAN-'.now()->format('Y').'-0001')
        ->status->toBe(PlanStatus::Draft)
        ->proposed_by_dai_id->toBe($this->dai->id)
        ->responsible_dai_id->toBe($this->dai->id)
        ->and(($this->propose)()->plan_number)->toEndWith('-0002');
});

it('refuses plans in the past, ending before they start, or from users without a da\'i record', function (): void {
    expectRuleViolation(fn () => ($this->propose)(['planned_date' => now()->subDay()->toDateString()]), 'planned_date');
    expectRuleViolation(fn () => ($this->propose)(['start_time' => '10:00', 'end_time' => '09:00']), 'end_time');

    $other = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    ($this->in)(fn () => expectRuleViolation(fn () => app(ManagePlans::class)->save(null, ['activity_type_id' => $this->type->id], $other), 'responsible_dai_id'));
});

it('runs a plan from draft to verified report with every step audited', function (): void {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $plan = ($this->toApproved)(($this->propose)(['planned_date' => now()->toDateString()]));

    ($this->in)(function () use ($plan): void {
        ($this->plans)()->schedule($plan, $this->daiUser);
        ($this->plans)()->start($plan, $this->daiUser);
        ($this->plans)()->complete($plan, $this->daiUser);
        app(ReportWorkflow::class)->save($plan, ['summary' => 'Forty visitors came', 'new_prospects_count' => 3], $this->daiUser);
        app(ReportWorkflow::class)->submit($plan, $this->daiUser);
        app(ReportWorkflow::class)->verify($plan, $this->head);
    });

    $plan = ($this->in)(fn () => $plan->refresh());
    expect($plan->status)->toBe(PlanStatus::Verified)
        ->and($plan->approved_by)->toBe($this->head->id)
        ->and(($this->in)(fn () => $plan->report()->first()))->status->toBe(ReportStatus::Verified)->new_prospects_count->toBe(3)
        ->and(AuditLog::query()->where('action', 'dawah_plan.status_changed')->count())->toBe(8);
});

it('returns a plan for revision with a note, after which only the da\'i edits and resubmits it', function (): void {
    $plan = ($this->propose)();

    ($this->in)(function () use ($plan): void {
        ($this->plans)()->submit($plan, $this->daiUser);
        expectRuleViolation(fn () => app(ManagePlans::class)->save($plan->refresh(), ['activity_type_id' => $this->type->id, 'planned_date' => now()->addWeek()->toDateString(), 'title' => 'x', 'objective' => 'y'], $this->daiUser), 'status');

        ($this->plans)()->startReview($plan, $this->head);
        expectRuleViolation(fn () => ($this->plans)()->requestRevision($plan, $this->head, ''), 'reason');
        ($this->plans)()->requestRevision($plan, $this->head, 'Add the location');

        app(ManagePlans::class)->save($plan->refresh(), ['activity_type_id' => $this->type->id, 'planned_date' => now()->addWeek()->toDateString(), 'title' => 'Open day', 'objective' => 'y', 'location' => 'Main hall'], $this->daiUser);
        ($this->plans)()->submit($plan, $this->daiUser);
    });

    expect(($this->in)(fn () => $plan->refresh()))->status->toBe(PlanStatus::Submitted)->location->toBe('Main hall')->revision_note->toBeNull();
});

it('keeps approval away from the plan\'s own da\'i and enforces the permissions of each step', function (): void {
    $plan = ($this->propose)();
    $selfApproving = grantRole($this->daiUser, 'head_of_dawah', $this->tenant);

    ($this->in)(function () use ($plan, $selfApproving): void {
        expect(fn () => ($this->plans)()->submit($plan, $this->head))->toThrow(AuthorizationException::class);
        ($this->plans)()->submit($plan, $this->daiUser);
        expect(fn () => ($this->plans)()->startReview($plan, grantRole(memberOf($this->tenant), 'dai', $this->tenant)))->toThrow(AuthorizationException::class);
        ($this->plans)()->startReview($plan, $this->head);
        expectRuleViolation(fn () => ($this->plans)()->approve($plan, $selfApproving), 'status');
        expectRuleViolation(fn () => ($this->plans)()->start($plan, $this->head), 'status');
    });
});

it('does not start before the planned day and cancels only with a reason until it starts', function (): void {
    $plan = ($this->toApproved)(($this->propose)());

    ($this->in)(function () use ($plan): void {
        ($this->plans)()->schedule($plan, $this->daiUser);
        expectRuleViolation(fn () => ($this->plans)()->start($plan, $this->daiUser), 'status');
        expect(fn () => ($this->plans)()->cancel($plan, $this->daiUser, 'Rain'))->toThrow(AuthorizationException::class);
        expectRuleViolation(fn () => ($this->plans)()->cancel($plan, $this->head, ' '), 'reason');
        ($this->plans)()->cancel($plan, $this->head, 'Venue unavailable');
    });

    expect(($this->in)(fn () => $plan->refresh()))->status->toBe(PlanStatus::Cancelled)->cancellation_reason->toBe('Venue unavailable');
});

it('returns a report for changes and lets the da\'i resubmit it', function (): void {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $plan = ($this->toApproved)(($this->propose)(['planned_date' => now()->toDateString()]));

    ($this->in)(function () use ($plan): void {
        foreach (['schedule', 'start', 'complete'] as $step) {
            ($this->plans)()->{$step}($plan, $this->daiUser);
        }
        expectRuleViolation(fn () => app(ReportWorkflow::class)->submit($plan, $this->daiUser), 'summary');
        app(ReportWorkflow::class)->save($plan, ['summary' => 'Short'], $this->daiUser);
        app(ReportWorkflow::class)->submit($plan, $this->daiUser);
        expect(fn () => app(ReportWorkflow::class)->verify($plan, $this->daiUser))->toThrow(AuthorizationException::class);
        app(ReportWorkflow::class)->return($plan, $this->head, 'Add the challenges');
        app(ReportWorkflow::class)->save($plan->refresh(), ['summary' => 'Longer', 'challenges' => 'Parking'], $this->daiUser);
        app(ReportWorkflow::class)->submit($plan, $this->daiUser);
    });

    expect(($this->in)(fn () => $plan->refresh()))->status->toBe(PlanStatus::ReportSubmitted)
        ->and(($this->in)(fn () => $plan->report()->first()))->status->toBe(ReportStatus::Submitted)->challenges->toBe('Parking');
});

it('counts attended participants and leaves high-confidentiality converts off printed lists', function (): void {
    $plan = ($this->propose)();

    ($this->in)(function () use ($plan): void {
        $open = Convert::factory()->create(['full_name' => 'Open Convert']);
        $secret = Convert::factory()->create(['full_name' => 'Secret Convert', 'disclosure_risk_level' => DisclosureLevel::HighConfidentiality]);
        $participants = app(ManageParticipants::class);

        $first = $participants->add($plan, ['participant_type' => 'convert', 'participant_id' => $open->id], $this->head);
        $participants->add($plan, ['participant_type' => 'convert', 'participant_id' => $secret->id], $this->head);
        $participants->add($plan, ['participant_type' => 'guest', 'participant_name' => 'Visitor', 'phone' => '0501234567'], $this->daiUser);
        expectRuleViolation(fn () => $participants->add($plan, ['participant_type' => 'convert', 'participant_id' => $open->id], $this->head), 'participant_id');
        expectRuleViolation(fn () => $participants->add($plan, ['participant_type' => 'guest'], $this->head), 'participant_name');
        expectRuleViolation(fn () => $participants->markAttendance($first, AttendanceStatus::Attended, $this->head), 'participant_type');

        $names = array_column(app(ParticipantDirectory::class)->printable($plan, $this->head), 'name');
        expect($names)->toBe(['Open Convert', 'Visitor']);
    });
});

it('lists only participants of plans in this office', function (): void {
    $plan = ($this->propose)();
    ($this->in)(fn () => app(ManageParticipants::class)->add($plan, ['participant_type' => 'guest', 'participant_name' => 'Visitor'], $this->daiUser));

    $other = Tenant::factory()->active()->create();
    expect(inTenant($other, fn () => ActivityParticipant::query()->count()))->toBe(0)
        ->and(($this->in)(fn () => ActivityParticipant::query()->count()))->toBe(1);
});
