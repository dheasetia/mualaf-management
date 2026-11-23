<?php

declare(strict_types=1);

namespace App\Domain\Transport\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\PickupDirectory;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Transport\Enums\PickupLocationType;
use App\Domain\Transport\Enums\PickupStatus;
use App\Domain\Transport\Exceptions\TransportRuleViolation;
use App\Domain\Transport\Models\ActivityTransportRequest;
use App\Domain\Transport\Models\TransportRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Pickups of a convert (transport.request, with the convert's full profile
 * in this office). The pickup point is copied from a residence, the current
 * workplace or typed in. A high-confidentiality convert appears on a pickup
 * sheet only after Head of Dawah approves it (04 §4.4).
 */
final readonly class PickupRequests
{
    public function __construct(private PickupDirectory $directory, private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function request(Convert $convert, array $data, User $actor): TransportRequest
    {
        if (! $actor->hasPermission('transport.request') || ! $actor->can('viewLocations', $convert)) {
            throw new AuthorizationException;
        }

        $pickupAt = CarbonImmutable::parse((string) $data['pickup_at']);

        if ($pickupAt->isPast()) {
            throw TransportRuleViolation::pickupInPast();
        }

        $type = PickupLocationType::from((string) $data['pickup_location_type']);
        $pickup = new TransportRequest;
        $pickup->convert_id = $convert->id;
        $pickup->pickup_location_type = $type;

        match ($type) {
            PickupLocationType::Residence => $this->fromResidence($pickup, $convert->id, (int) ($data['residence_id'] ?? 0)),
            PickupLocationType::Workplace => $this->fromWorkplace($pickup, $convert->id),
            PickupLocationType::Custom => $this->fromCustom($pickup, $data),
        };

        $contactId = blank($data['contact_person_id'] ?? null) ? null : (int) $data['contact_person_id'];

        if ($contactId !== null && ! $this->directory->isContactable($convert->id, $contactId)) {
            throw TransportRuleViolation::contactNotAllowed();
        }

        $planId = blank($data['activity_plan_id'] ?? null) ? null : (int) $data['activity_plan_id'];
        $groupId = blank($data['activity_transport_request_id'] ?? null) ? null : (int) $data['activity_transport_request_id'];

        if ($planId !== null && ! DawahActivityPlan::query()->whereKey($planId)->exists()) {
            throw TransportRuleViolation::planNotOpen();
        }

        if ($groupId !== null && ! ActivityTransportRequest::query()->whereKey($groupId)->exists()) {
            throw TransportRuleViolation::transportRequestNotApproved();
        }

        $pickup->contact_person_id = $contactId;
        $pickup->activity_plan_id = $planId;
        $pickup->activity_transport_request_id = $groupId;
        $pickup->pickup_at = $pickupAt;
        $pickup->notes = blank($data['notes'] ?? null) ? null : (string) $data['notes'];
        $pickup->status = PickupStatus::Requested;
        $pickup->requested_by = $actor->id;
        $pickup->save();

        $this->audit->record('pickup.requested', $convert, newValues: ['pickup_id' => $pickup->id], actor: $actor);

        return $pickup;
    }

    /** Head of Dawah lets a high-confidentiality convert be printed on a pickup sheet. */
    public function approveDisclosure(TransportRequest $pickup, User $actor): TransportRequest
    {
        if (! $actor->hasPermission('converts.view_high_confidentiality')) {
            throw new AuthorizationException;
        }

        if (! $this->directory->isHighConfidentiality($pickup->convert_id)) {
            throw TransportRuleViolation::disclosureNotNeeded();
        }

        $pickup->disclosure_approved_by = $actor->id;
        $pickup->save();

        $this->audit->record('pickup.disclosure_approved', $pickup, actor: $actor);

        return $pickup;
    }

    public function cancel(TransportRequest $pickup, User $actor): TransportRequest
    {
        if ($pickup->requested_by !== $actor->id && ! $actor->hasPermission('transport.dispatch')) {
            throw new AuthorizationException;
        }

        if (! $pickup->status->canTransitionTo(PickupStatus::Cancelled)) {
            throw TransportRuleViolation::invalidTransition($pickup->status->label(), PickupStatus::Cancelled->label());
        }

        $pickup->status = PickupStatus::Cancelled;
        $pickup->trip_id = null;
        $pickup->vehicle_id = null;
        $pickup->driver_id = null;
        $pickup->save();

        $this->audit->record('pickup.cancelled', $pickup, actor: $actor);

        return $pickup;
    }

    private function fromResidence(TransportRequest $pickup, int $convertId, int $residenceId): void
    {
        $point = $this->directory->residencePoint($convertId, $residenceId) ?? throw TransportRuleViolation::pickupPointMissing();
        $pickup->residence_id = $point['residence_id'];
        $pickup->latitude = $point['latitude'];
        $pickup->longitude = $point['longitude'];
        $pickup->pickup_address = $point['address'] === '' ? null : $point['address'];
    }

    private function fromWorkplace(TransportRequest $pickup, int $convertId): void
    {
        $point = $this->directory->workplacePoint($convertId) ?? throw TransportRuleViolation::pickupPointMissing();
        $pickup->latitude = $point['latitude'];
        $pickup->longitude = $point['longitude'];
        $pickup->pickup_address = $point['address'] === '' ? null : $point['address'];
    }

    /** @param  array<string, mixed>  $data */
    private function fromCustom(TransportRequest $pickup, array $data): void
    {
        $hasCoordinates = ! blank($data['latitude'] ?? null) && ! blank($data['longitude'] ?? null);

        if (blank($data['pickup_address'] ?? null) && ! $hasCoordinates) {
            throw TransportRuleViolation::pickupPointMissing();
        }

        $pickup->pickup_address = blank($data['pickup_address'] ?? null) ? null : trim((string) $data['pickup_address']);
        $pickup->latitude = $hasCoordinates ? (string) $data['latitude'] : null;
        $pickup->longitude = $hasCoordinates ? (string) $data['longitude'] : null;
    }
}
