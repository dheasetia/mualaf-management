<?php

declare(strict_types=1);

namespace App\Domain\Transport\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class TransportRuleViolation extends RuleViolation
{
    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('transport.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('transport.errors.reason_required'), 'reason');
    }

    public static function plateTaken(): self
    {
        return new self(__('transport.errors.plate_taken'), 'plate_number');
    }

    public static function planNotOpen(): self
    {
        return new self(__('transport.errors.plan_not_open'), 'activity_plan_id');
    }

    public static function selfDecision(): self
    {
        return new self(__('transport.errors.self_decision'), 'status');
    }

    public static function returnBeforeDeparture(): self
    {
        return new self(__('transport.errors.return_before_departure'), 'returns_at');
    }

    public static function pickupPointMissing(): self
    {
        return new self(__('transport.errors.pickup_point_missing'), 'pickup_location_type');
    }

    public static function contactNotAllowed(): self
    {
        return new self(__('transport.errors.contact_not_allowed'), 'contact_person_id');
    }

    public static function pickupInPast(): self
    {
        return new self(__('transport.errors.pickup_in_past'), 'pickup_at');
    }

    public static function vehicleUnavailable(): self
    {
        return new self(__('transport.errors.vehicle_unavailable'), 'vehicle_id');
    }

    public static function driverUnavailable(): self
    {
        return new self(__('transport.errors.driver_unavailable'), 'driver_id');
    }

    public static function licenseExpired(): self
    {
        return new self(__('transport.errors.license_expired'), 'driver_id');
    }

    public static function vehicleBusy(): self
    {
        return new self(__('transport.errors.vehicle_busy'), 'vehicle_id');
    }

    public static function driverBusy(): self
    {
        return new self(__('transport.errors.driver_busy'), 'driver_id');
    }

    public static function tripFull(): self
    {
        return new self(__('transport.errors.trip_full'), 'trip_id');
    }

    public static function pickupOutsideTrip(): self
    {
        return new self(__('transport.errors.pickup_outside_trip'), 'trip_id');
    }

    public static function tripNotOpen(): self
    {
        return new self(__('transport.errors.trip_not_open'), 'trip_id');
    }

    public static function pickupsPending(): self
    {
        return new self(__('transport.errors.pickups_pending'), 'status');
    }

    public static function transportRequestNotApproved(): self
    {
        return new self(__('transport.errors.transport_request_not_approved'), 'activity_transport_request_id');
    }

    public static function disclosureNotNeeded(): self
    {
        return new self(__('transport.errors.disclosure_not_needed'), 'status');
    }
}
