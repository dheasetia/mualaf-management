<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Actions;

use App\Domain\Dawah\Enums\AttendanceStatus;
use App\Domain\Dawah\Enums\ParticipantType;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Exceptions\DawahRuleViolation;
use App\Domain\Dawah\Models\ActivityParticipant;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Domain\Dawah\Services\ParticipantDirectory;
use App\Models\User;
use App\Support\Identity\PhoneNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Participants of a plan (08 §8.5): listed by its da'i or an approver until
 * the activity starts; attendance is marked while it runs and until the
 * report is submitted.
 */
final readonly class ManageParticipants
{
    private const array OPEN_FOR_LIST = [PlanStatus::Draft, PlanStatus::Submitted, PlanStatus::SupervisorReview, PlanStatus::NeedsRevision, PlanStatus::Approved, PlanStatus::Scheduled];

    private const array OPEN_FOR_ATTENDANCE = [PlanStatus::Scheduled, PlanStatus::InProgress, PlanStatus::Completed];

    public function __construct(private DawahActors $actors, private ParticipantDirectory $directory) {}

    /** @param  array{participant_type: string, participant_id?: int|string|null, participant_name?: string|null, phone?: string|null, transportation_required?: bool, notes?: string|null}  $data */
    public function add(DawahActivityPlan $plan, array $data, User $actor): ActivityParticipant
    {
        $this->authorize($plan, $actor);

        if (! in_array($plan->status, self::OPEN_FOR_LIST, true)) {
            throw DawahRuleViolation::participantsClosed();
        }

        $type = ParticipantType::from($data['participant_type']);
        $participant = new ActivityParticipant;
        $participant->activity_plan_id = $plan->id;
        $participant->participant_type = $type;

        if ($type->isLinked()) {
            $id = (int) ($data['participant_id'] ?? 0);

            if (! $this->directory->canInvite($type, $id, $actor)) {
                throw DawahRuleViolation::participantUnavailable();
            }

            $participant->participant_id = $id;
        } else {
            $participant->participant_name = blank($data['participant_name'] ?? null) ? throw DawahRuleViolation::participantNameRequired() : trim((string) $data['participant_name']);
            $participant->phone_encrypted = PhoneNumber::normalize($data['phone'] ?? null);
        }

        $participant->transportation_required = (bool) ($data['transportation_required'] ?? false);
        $participant->notes = blank($data['notes'] ?? null) ? null : (string) $data['notes'];
        $participant->attendance_status = AttendanceStatus::Invited;

        try {
            $participant->save();
        } catch (UniqueConstraintViolationException) {
            throw DawahRuleViolation::alreadyParticipant();
        }

        return $participant;
    }

    public function remove(ActivityParticipant $participant, User $actor): void
    {
        $plan = $participant->plan()->firstOrFail();
        $this->authorize($plan, $actor);

        if (! in_array($plan->status, self::OPEN_FOR_LIST, true)) {
            throw DawahRuleViolation::participantsClosed();
        }

        $participant->delete();
    }

    public function markAttendance(ActivityParticipant $participant, AttendanceStatus $status, User $actor): ActivityParticipant
    {
        $plan = $participant->plan()->firstOrFail();
        $this->authorize($plan, $actor);

        $isRsvp = in_array($status, [AttendanceStatus::Invited, AttendanceStatus::Confirmed, AttendanceStatus::Cancelled], true);
        $open = $isRsvp ? [...self::OPEN_FOR_LIST, ...self::OPEN_FOR_ATTENDANCE] : self::OPEN_FOR_ATTENDANCE;

        if (! in_array($plan->status, $open, true)) {
            throw DawahRuleViolation::participantsClosed();
        }

        $participant->attendance_status = $status;
        $participant->save();

        return $participant;
    }

    private function authorize(DawahActivityPlan $plan, User $actor): void
    {
        $isPlanDai = $actor->hasPermission('dawah.plans.create') && $this->actors->isPlanDai($actor, $plan);

        if (! $isPlanDai && ! $actor->hasPermission('dawah.plans.approve')) {
            throw new AuthorizationException;
        }
    }
}
