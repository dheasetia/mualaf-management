<?php

declare(strict_types=1);

use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transport\Actions\ActivityTransportWorkflow;
use App\Domain\Transport\Actions\Dispatch;
use App\Domain\Transport\Enums\ActivityTransportStatus;
use App\Domain\Transport\Enums\PickupStatus;
use App\Domain\Transport\Enums\TripStatus;
use App\Domain\Transport\Enums\VehicleStatus;
use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\TransportRequest;
use App\Domain\Transport\Models\TransportTrip;
use App\Domain\Transport\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create();
    $this->officer = grantRole(memberOf($this->tenant), 'transportation_officer', $this->tenant);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    [$this->vehicle, $this->driver] = ($this->in)(fn () => [Vehicle::factory()->create(['capacity' => 2]), Driver::factory()->create()]);
    $this->start = now()->addDays(2)->setTime(9, 0);
    $this->trip = fn (array $overrides = []) => ($this->in)(fn () => app(Dispatch::class)->createTrip([
        'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id,
        'departs_at' => $this->start->toIso8601String(), 'returns_at' => $this->start->copy()->addHours(3)->toIso8601String(), ...$overrides,
    ], $this->officer));
    $this->pickup = fn (array $overrides = []) => ($this->in)(fn () => TransportRequest::factory()->create(['pickup_at' => $this->start->copy()->addMinutes(30), ...$overrides]));
});

it('refuses a vehicle or driver that already has an overlapping open trip, and allows back-to-back trips', function (): void {
    ($this->trip)();
    $otherVehicle = ($this->in)(fn () => Vehicle::factory()->create());
    $otherDriver = ($this->in)(fn () => Driver::factory()->create());

    expectRuleViolation(fn () => ($this->trip)(['driver_id' => $otherDriver->id, 'departs_at' => $this->start->copy()->addHours(2)->toIso8601String()]), 'vehicle_id');
    expectRuleViolation(fn () => ($this->trip)(['vehicle_id' => $otherVehicle->id, 'departs_at' => $this->start->copy()->addHour()->toIso8601String()]), 'driver_id');

    $next = ($this->trip)(['departs_at' => $this->start->copy()->addHours(3)->toIso8601String(), 'returns_at' => $this->start->copy()->addHours(5)->toIso8601String()]);
    expect($next->status)->toBe(TripStatus::Planned);
});

it('needs an available vehicle and a driver with a licence valid on the trip day', function (): void {
    ($this->in)(fn () => $this->vehicle->forceFill(['status' => VehicleStatus::InMaintenance])->save());
    expectRuleViolation(fn () => ($this->trip)(), 'vehicle_id');

    ($this->in)(function (): void {
        $this->vehicle->forceFill(['status' => VehicleStatus::Available])->save();
        $this->driver->forceFill(['license_expires_on' => now()->addDay()->toDateString()])->save();
    });
    expectRuleViolation(fn () => ($this->trip)(), 'driver_id');
    expectRuleViolation(fn () => ($this->trip)(['returns_at' => $this->start->copy()->subHour()->toIso8601String()]), 'returns_at');
});

it('seats pickups within the trip time up to the vehicle capacity', function (): void {
    $trip = ($this->trip)();

    ($this->in)(function () use ($trip): void {
        $dispatch = app(Dispatch::class);
        $dispatch->assign(($this->pickup)(), $trip, $this->officer);
        $dispatch->assign(($this->pickup)(), $trip, $this->officer);

        expectRuleViolation(fn () => $dispatch->assign(($this->pickup)(), $trip, $this->officer), 'trip_id');
        expectRuleViolation(fn () => $dispatch->assign(($this->pickup)(['pickup_at' => $this->start->copy()->addHours(5)]), $trip, $this->officer), 'trip_id');
    });
});

it('completes a trip only when every pickup is marked, and cancelling returns pickups to the queue', function (): void {
    $trip = ($this->trip)();

    ($this->in)(function () use ($trip): void {
        $dispatch = app(Dispatch::class);
        $first = $dispatch->assign(($this->pickup)(), $trip, $this->officer);
        $second = $dispatch->assign(($this->pickup)(), $trip, $this->officer);

        expectRuleViolation(fn () => $dispatch->mark($first, PickupStatus::PickedUp, $this->officer), 'trip_id');
        $dispatch->start($trip, $this->officer);
        $dispatch->mark($first, PickupStatus::PickedUp, $this->officer);
        expectRuleViolation(fn () => $dispatch->complete($trip, $this->officer), 'status');
        $dispatch->mark($second, PickupStatus::NoShow, $this->officer);
        $dispatch->complete($trip, $this->officer);

        expect($first->refresh()->status)->toBe(PickupStatus::Completed)
            ->and($second->refresh()->status)->toBe(PickupStatus::NoShow);

        $later = app(Dispatch::class)->createTrip([
            'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id,
            'departs_at' => $this->start->copy()->addDay()->toIso8601String(), 'returns_at' => $this->start->copy()->addDay()->addHours(2)->toIso8601String(),
        ], $this->officer);
        $waiting = $dispatch->assign(($this->pickup)(['pickup_at' => $this->start->copy()->addDay()->addMinutes(15)]), $later, $this->officer);
        expectRuleViolation(fn () => $dispatch->cancel($later, $this->officer, ''), 'reason');
        $dispatch->cancel($later, $this->officer, 'Vehicle broke down');

        expect($waiting->refresh())->status->toBe(PickupStatus::Requested)->trip_id->toBeNull();
    });
});

it('decides activity transport by someone other than the requester and schedules it with its trip', function (): void {
    $daiRequester = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    $plan = ($this->in)(fn () => DawahActivityPlan::factory()->create());
    $workflow = fn (): ActivityTransportWorkflow => app(ActivityTransportWorkflow::class);

    ($this->in)(function () use ($plan, $workflow, $daiRequester): void {
        $own = $workflow()->request($plan, ['transport_type' => 'office_vehicle', 'passenger_count' => 6, 'departure_at' => $this->start->toIso8601String()], $this->officer);
        expectRuleViolation(fn () => $workflow()->approve($own, $this->officer), 'status');

        $request = $workflow()->request($plan, ['transport_type' => 'office_vehicle', 'passenger_count' => 6, 'departure_at' => $this->start->toIso8601String()], $daiRequester);
        expect(fn () => $workflow()->approve($request, $daiRequester))->toThrow(AuthorizationException::class);
        expectRuleViolation(fn () => ($this->trip)(['activity_transport_request_id' => $request->id]), 'activity_transport_request_id');

        $workflow()->approve($request, $this->officer);
        $trip = ($this->trip)(['activity_transport_request_id' => $request->id]);

        expect($request->refresh()->status)->toBe(ActivityTransportStatus::Scheduled)
            ->and($trip->activity_transport_request_id)->toBe($request->id);
    });
});

it('lets only dispatch create trips', function (): void {
    $dai = grantRole(memberOf($this->tenant), 'dai', $this->tenant);

    ($this->in)(fn () => expect(fn () => app(Dispatch::class)->createTrip([
        'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id,
        'departs_at' => $this->start->toIso8601String(), 'returns_at' => $this->start->copy()->addHour()->toIso8601String(),
    ], $dai))->toThrow(AuthorizationException::class));

    expect(($this->in)(fn () => TransportTrip::query()->count()))->toBe(0);
});
