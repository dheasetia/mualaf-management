<?php

declare(strict_types=1);

namespace App\Domain\Transport\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Transport\Enums\ActivityTransportStatus;
use App\Domain\Transport\Enums\TransportType;
use App\Domain\Transport\Exceptions\TransportRuleViolation;
use App\Domain\Transport\Models\ActivityTransportRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Transport for a dawah activity (transport.request): approved or rejected
 * with a reason by transport.approve (not the requester), scheduled when
 * a trip is created for it, completed by dispatch, cancelled with a reason.
 */
final readonly class ActivityTransportWorkflow
{
    private const array CLOSED_PLANS = [PlanStatus::Rejected, PlanStatus::Cancelled, PlanStatus::InProgress, PlanStatus::Completed, PlanStatus::ReportSubmitted, PlanStatus::Verified];

    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function request(DawahActivityPlan $plan, array $data, User $actor): ActivityTransportRequest
    {
        $this->require($actor->hasPermission('transport.request'));

        if (in_array($plan->status, self::CLOSED_PLANS, true)) {
            throw TransportRuleViolation::planNotOpen();
        }

        $departure = CarbonImmutable::parse((string) $data['departure_at']);
        $return = blank($data['return_at'] ?? null) ? null : CarbonImmutable::parse((string) $data['return_at']);

        if ($return !== null && $return->lessThanOrEqualTo($departure)) {
            throw TransportRuleViolation::returnBeforeDeparture();
        }

        $request = new ActivityTransportRequest;
        $request->activity_plan_id = $plan->id;
        $request->requested_by = $actor->id;
        $request->transport_type = TransportType::from((string) $data['transport_type']);
        $request->vehicle_count = max(1, (int) ($data['vehicle_count'] ?? 1));
        $request->passenger_count = max(1, (int) $data['passenger_count']);
        $request->pickup_required = (bool) ($data['pickup_required'] ?? false);
        $request->departure_at = $departure;
        $request->return_at = $return;
        $request->notes = blank($data['notes'] ?? null) ? null : (string) $data['notes'];
        $request->status = ActivityTransportStatus::Requested;
        $request->save();

        $this->audit->record('activity_transport.requested', $request, newValues: ['plan_id' => $plan->id], actor: $actor);

        return $request;
    }

    public function approve(ActivityTransportRequest $request, User $actor): ActivityTransportRequest
    {
        $this->requireDecider($request, $actor);

        return $this->move($request, ActivityTransportStatus::Approved, $actor, function (ActivityTransportRequest $locked) use ($actor): void {
            $locked->approved_by = $actor->id;
            $locked->decided_at = CarbonImmutable::now();
        });
    }

    public function reject(ActivityTransportRequest $request, User $actor, ?string $reason): ActivityTransportRequest
    {
        $this->requireDecider($request, $actor);
        $reason = blank($reason) ? throw TransportRuleViolation::reasonRequired() : trim((string) $reason);

        return $this->move($request, ActivityTransportStatus::Rejected, $actor, function (ActivityTransportRequest $locked) use ($actor, $reason): void {
            $locked->approved_by = $actor->id;
            $locked->decided_at = CarbonImmutable::now();
            $locked->decision_reason = $reason;
        });
    }

    public function cancel(ActivityTransportRequest $request, User $actor, ?string $reason): ActivityTransportRequest
    {
        $this->require($request->requested_by === $actor->id || $actor->hasPermission('transport.approve'));
        $reason = blank($reason) ? throw TransportRuleViolation::reasonRequired() : trim((string) $reason);

        return $this->move($request, ActivityTransportStatus::Cancelled, $actor, fn (ActivityTransportRequest $locked) => $locked->decision_reason = $reason);
    }

    public function complete(ActivityTransportRequest $request, User $actor): ActivityTransportRequest
    {
        $this->require($actor->hasPermission('transport.dispatch'));

        return $this->move($request, ActivityTransportStatus::Completed, $actor);
    }

    /** Called when dispatch plans a trip for an approved request. */
    public function markScheduled(ActivityTransportRequest $request, User $actor): void
    {
        if ($request->status === ActivityTransportStatus::Approved) {
            $this->move($request, ActivityTransportStatus::Scheduled, $actor);
        }
    }

    /** @param  (Closure(ActivityTransportRequest): mixed)|null  $apply */
    private function move(ActivityTransportRequest $request, ActivityTransportStatus $target, User $actor, ?Closure $apply = null): ActivityTransportRequest
    {
        return DB::transaction(function () use ($request, $target, $actor, $apply): ActivityTransportRequest {
            $locked = ActivityTransportRequest::query()->lockForUpdate()->findOrFail($request->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw TransportRuleViolation::invalidTransition($from->label(), $target->label());
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->status = $target;
            $locked->save();

            $this->audit->record('activity_transport.status_changed', $locked, ['status' => $from->value], ['status' => $target->value], actor: $actor);

            return $locked;
        });
    }

    private function requireDecider(ActivityTransportRequest $request, User $actor): void
    {
        $this->require($actor->hasPermission('transport.approve'));

        if ($request->requested_by === $actor->id) {
            throw TransportRuleViolation::selfDecision();
        }
    }

    private function require(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
