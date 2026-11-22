<?php

declare(strict_types=1);

namespace App\Domain\Dai\Actions;

use App\Domain\Dai\Contracts\AssignableSubject;
use App\Domain\Dai\Data\FollowUpData;
use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a da'i follow-up. The subject's module wraps this to update its own
 * contact dates (for prospects: RecordProspectFollowUp).
 */
final class RecordFollowUp
{
    /**
     * @param  Model&AssignableSubject  $subject
     */
    public function handle(Model $subject, int $daiId, FollowUpData $data, User $actor): DaiFollowUp
    {
        $dai = Dai::query()->find($daiId);

        if (! $dai instanceof Dai || $dai->status === DaiStatus::Inactive || $dai->tenant_id !== $subject->responsibleTenantId()) {
            throw DaiRuleViolation::assigneeUnavailable();
        }

        $followUp = new DaiFollowUp;
        $followUp->dai_id = $dai->id;
        $followUp->subject_type = $subject->getMorphClass();
        $followUp->subject_id = (int) $subject->getKey();
        $followUp->follow_up_type = $data->type;
        $followUp->contact_method = $data->contactMethod;
        $followUp->occurred_at = $data->occurredAt;
        $followUp->duration_minutes = $data->durationMinutes;
        $followUp->location = $data->location;
        $followUp->summary = $data->summary;
        $followUp->questions_raised = $data->questionsRaised;
        $followUp->outcome = $data->outcome;
        $followUp->next_action = $data->nextAction;
        $followUp->next_follow_up_at = $data->nextFollowUpAt;
        $followUp->confidentiality_level = $data->confidentiality;
        $followUp->created_by = $actor->id;
        $followUp->save();

        return $followUp;
    }
}
