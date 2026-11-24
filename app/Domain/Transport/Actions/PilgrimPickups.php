<?php

declare(strict_types=1);

namespace App\Domain\Transport\Actions;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\PickupDirectory;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Pilgrimage\Services\ProgramDirectory;
use App\Domain\Transport\Enums\PickupLocationType;
use App\Domain\Transport\Enums\PickupStatus;
use App\Domain\Transport\Exceptions\TransportRuleViolation;
use App\Domain\Transport\Models\TransportRequest;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Pickups for everyone travelling with a pilgrimage program (approved or
 * confirmed), for an activity linked to that program (10 §10.2). Each goes
 * through PickupRequests with the same checks as a single pickup, from the
 * convert's pickup-point or current residence. Pilgrims already on this
 * activity, without a residence, or whose profile the requester may not
 * see are skipped and counted.
 */
final readonly class PilgrimPickups
{
    public function __construct(private PickupRequests $pickups, private PickupDirectory $directory, private ProgramDirectory $programs) {}

    /** @return array{created: int, skipped: int} */
    public function requestForProgram(int $programId, DawahActivityPlan $plan, CarbonImmutable $pickupAt, ?int $activityTransportRequestId, User $actor): array
    {
        if (! $actor->hasPermission('transport.request')) {
            throw new AuthorizationException;
        }

        if ($plan->pilgrimage_program_id !== $programId) {
            throw TransportRuleViolation::planNotOpen();
        }

        if ($pickupAt->isPast()) {
            throw TransportRuleViolation::pickupInPast();
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->programs->travellingConvertIds($programId) as $convertId) {
            $residenceId = $this->directory->defaultResidenceId($convertId);
            $already = TransportRequest::query()->where('convert_id', $convertId)->where('activity_plan_id', $plan->id)
                ->where('status', '!=', PickupStatus::Cancelled)->exists();

            if ($residenceId === null || $already) {
                $skipped++;

                continue;
            }

            try {
                $this->pickups->request(Convert::query()->findOrFail($convertId), [
                    'pickup_location_type' => PickupLocationType::Residence->value,
                    'residence_id' => $residenceId,
                    'pickup_at' => $pickupAt->toIso8601String(),
                    'activity_plan_id' => $plan->id,
                    'activity_transport_request_id' => $activityTransportRequestId,
                ], $actor);
                $created++;
            } catch (AuthorizationException|RuleViolation) {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
