<?php

declare(strict_types=1);

namespace App\Domain\Transport\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Transport\Enums\ActivityTransportStatus;
use App\Domain\Transport\Enums\DriverStatus;
use App\Domain\Transport\Enums\PickupStatus;
use App\Domain\Transport\Enums\TripStatus;
use App\Domain\Transport\Enums\VehicleStatus;
use App\Domain\Transport\Exceptions\TransportRuleViolation;
use App\Domain\Transport\Models\ActivityTransportRequest;
use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\TransportRequest;
use App\Domain\Transport\Models\TransportTrip;
use App\Domain\Transport\Models\Vehicle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Trips and pickup assignment (transport.dispatch, 08 §8.7):
 * - a trip needs an available vehicle and an active driver whose licence
 *   is valid on the day; vehicle and driver rows are locked and neither may
 *   have another open trip overlapping the time;
 * - pickups join a planned trip within its time and up to the vehicle's
 *   capacity;
 * - a trip completes only when every pickup on it is marked; cancelling a
 *   planned trip returns its pickups to the queue.
 */
final readonly class Dispatch
{
    private const array OPEN_TRIPS = [TripStatus::Planned, TripStatus::InProgress];

    private const array SEATED = [PickupStatus::Assigned, PickupStatus::PickedUp];

    public function __construct(private ActivityTransportWorkflow $activityTransport, private AuditLogger $audit) {}

    /** @param  array{vehicle_id: int|string, driver_id: int|string, departs_at: string, returns_at: string, activity_transport_request_id?: int|string|null}  $data */
    public function createTrip(array $data, User $actor): TransportTrip
    {
        $this->authorize($actor);

        $departs = CarbonImmutable::parse($data['departs_at']);
        $returns = CarbonImmutable::parse($data['returns_at']);

        if ($returns->lessThanOrEqualTo($departs)) {
            throw TransportRuleViolation::returnBeforeDeparture();
        }

        $groupId = blank($data['activity_transport_request_id'] ?? null) ? null : (int) $data['activity_transport_request_id'];

        return DB::transaction(function () use ($data, $departs, $returns, $groupId, $actor): TransportTrip {
            $vehicle = Vehicle::query()->lockForUpdate()->find((int) $data['vehicle_id']);
            $driver = Driver::query()->lockForUpdate()->find((int) $data['driver_id']);

            if ($vehicle === null || $vehicle->status !== VehicleStatus::Available) {
                throw TransportRuleViolation::vehicleUnavailable();
            }

            if ($driver === null || $driver->status !== DriverStatus::Active) {
                throw TransportRuleViolation::driverUnavailable();
            }

            $validOn = $returns->setTimezone((string) config('app.display_timezone'))->toDateString();

            if ($driver->license_expires_on === null || $driver->license_expires_on->toDateString() < $validOn) {
                throw TransportRuleViolation::licenseExpired();
            }

            if ($this->overlaps('vehicle_id', $vehicle->id, $departs, $returns)) {
                throw TransportRuleViolation::vehicleBusy();
            }

            if ($this->overlaps('driver_id', $driver->id, $departs, $returns)) {
                throw TransportRuleViolation::driverBusy();
            }

            $group = $groupId === null ? null : ActivityTransportRequest::query()->find($groupId);

            if ($groupId !== null && ! in_array($group?->status, [ActivityTransportStatus::Approved, ActivityTransportStatus::Scheduled], true)) {
                throw TransportRuleViolation::transportRequestNotApproved();
            }

            $trip = new TransportTrip;
            $trip->vehicle_id = $vehicle->id;
            $trip->driver_id = $driver->id;
            $trip->activity_transport_request_id = $groupId;
            $trip->departs_at = $departs;
            $trip->returns_at = $returns;
            $trip->status = TripStatus::Planned;
            $trip->created_by = $actor->id;
            $trip->save();

            if ($group !== null) {
                $this->activityTransport->markScheduled($group, $actor);
            }

            $this->audit->record('trip.created', $trip, newValues: ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id], actor: $actor);

            return $trip;
        });
    }

    public function assign(TransportRequest $pickup, TransportTrip $trip, User $actor): TransportRequest
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($pickup, $trip, $actor): TransportRequest {
            $lockedTrip = TransportTrip::query()->with('vehicle')->lockForUpdate()->findOrFail($trip->id);
            $lockedPickup = TransportRequest::query()->lockForUpdate()->findOrFail($pickup->id);

            if ($lockedTrip->status !== TripStatus::Planned) {
                throw TransportRuleViolation::tripNotOpen();
            }

            if ($lockedPickup->status !== PickupStatus::Requested) {
                throw TransportRuleViolation::invalidTransition($lockedPickup->status->label(), PickupStatus::Assigned->label());
            }

            if ($lockedPickup->pickup_at->lessThan($lockedTrip->departs_at) || $lockedPickup->pickup_at->greaterThan($lockedTrip->returns_at)) {
                throw TransportRuleViolation::pickupOutsideTrip();
            }

            if ($lockedTrip->pickups()->whereIn('status', self::SEATED)->count() >= $lockedTrip->vehicle->capacity) {
                throw TransportRuleViolation::tripFull();
            }

            $lockedPickup->trip_id = $lockedTrip->id;
            $lockedPickup->vehicle_id = $lockedTrip->vehicle_id;
            $lockedPickup->driver_id = $lockedTrip->driver_id;
            $lockedPickup->status = PickupStatus::Assigned;
            $lockedPickup->save();

            $this->audit->record('pickup.assigned', $lockedPickup, newValues: ['trip_id' => $lockedTrip->id], actor: $actor);

            return $lockedPickup;
        });
    }

    public function unassign(TransportRequest $pickup, User $actor): TransportRequest
    {
        $this->authorize($actor);

        if ($pickup->status !== PickupStatus::Assigned) {
            throw TransportRuleViolation::invalidTransition($pickup->status->label(), PickupStatus::Requested->label());
        }

        $pickup->trip_id = null;
        $pickup->vehicle_id = null;
        $pickup->driver_id = null;
        $pickup->status = PickupStatus::Requested;
        $pickup->save();

        return $pickup;
    }

    /** Marks a pickup on a running trip: picked up or no-show. */
    public function mark(TransportRequest $pickup, PickupStatus $outcome, User $actor): TransportRequest
    {
        $this->authorize($actor);

        $trip = $pickup->trip_id === null ? null : TransportTrip::query()->find($pickup->trip_id);

        if ($trip?->status !== TripStatus::InProgress || ! in_array($outcome, [PickupStatus::PickedUp, PickupStatus::NoShow], true)) {
            throw TransportRuleViolation::tripNotOpen();
        }

        if (! $pickup->status->canTransitionTo($outcome)) {
            throw TransportRuleViolation::invalidTransition($pickup->status->label(), $outcome->label());
        }

        $pickup->status = $outcome;
        $pickup->save();

        return $pickup;
    }

    public function start(TransportTrip $trip, User $actor): TransportTrip
    {
        $this->authorize($actor);

        return $this->move($trip, TripStatus::InProgress, $actor);
    }

    public function complete(TransportTrip $trip, User $actor): TransportTrip
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($trip, $actor): TransportTrip {
            if ($trip->pickups()->where('status', PickupStatus::Assigned)->exists()) {
                throw TransportRuleViolation::pickupsPending();
            }

            $trip->pickups()->where('status', PickupStatus::PickedUp)->update(['status' => PickupStatus::Completed]);

            return $this->move($trip, TripStatus::Completed, $actor);
        });
    }

    public function cancel(TransportTrip $trip, User $actor, ?string $reason): TransportTrip
    {
        $this->authorize($actor);
        $reason = blank($reason) ? throw TransportRuleViolation::reasonRequired() : trim((string) $reason);

        return DB::transaction(function () use ($trip, $actor, $reason): TransportTrip {
            $cancelled = $this->move($trip, TripStatus::Cancelled, $actor, $reason);

            $trip->pickups()->where('status', PickupStatus::Assigned)
                ->update(['status' => PickupStatus::Requested, 'trip_id' => null, 'vehicle_id' => null, 'driver_id' => null]);

            return $cancelled;
        });
    }

    private function move(TransportTrip $trip, TripStatus $target, User $actor, ?string $reason = null): TransportTrip
    {
        return DB::transaction(function () use ($trip, $target, $actor, $reason): TransportTrip {
            $locked = TransportTrip::query()->lockForUpdate()->findOrFail($trip->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw TransportRuleViolation::invalidTransition($from->label(), $target->label());
            }

            $locked->status = $target;
            $locked->cancellation_reason = $reason ?? $locked->cancellation_reason;
            $locked->save();

            $this->audit->record('trip.status_changed', $locked, ['status' => $from->value], array_filter(['status' => $target->value, 'reason' => $reason]), actor: $actor);

            return $locked;
        });
    }

    private function overlaps(string $column, int $id, CarbonImmutable $departs, CarbonImmutable $returns): bool
    {
        return TransportTrip::query()
            ->where($column, $id)
            ->whereIn('status', self::OPEN_TRIPS)
            ->where('departs_at', '<', $returns)
            ->where('returns_at', '>', $departs)
            ->exists();
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('transport.dispatch')) {
            throw new AuthorizationException;
        }
    }
}
