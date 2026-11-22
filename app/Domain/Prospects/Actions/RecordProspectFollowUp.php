<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Actions;

use App\Domain\Dai\Actions\RecordFollowUp;
use App\Domain\Dai\Data\FollowUpData;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Services\ProspectStateMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records a follow-up by the acting user's da'i record and keeps the
 * prospect's contact dates current. The first follow-up of a new prospect
 * moves it to contacted.
 */
final readonly class RecordProspectFollowUp
{
    public function __construct(
        private RecordFollowUp $recordFollowUp,
        private DaiDirectory $directory,
        private DaiAssignmentService $assignments,
        private ProspectStateMachine $stateMachine,
    ) {}

    public function handle(Prospect $prospect, FollowUpData $data, User $actor): void
    {
        if ($prospect->status->isTerminal()) {
            throw ProspectRuleViolation::closed();
        }

        $daiId = $this->directory->daiIdForUser($actor) ?? throw DaiRuleViolation::notADai();

        if (! $actor->hasPermission('prospects.view') && ! $this->assignments->isAssigned($prospect, $daiId)) {
            throw ProspectRuleViolation::notAssigned();
        }

        DB::transaction(function () use ($prospect, $data, $actor, $daiId): void {
            $locked = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);

            if ($locked->status->isTerminal()) {
                throw ProspectRuleViolation::closed();
            }

            $this->recordFollowUp->handle($locked, $daiId, $data, $actor);

            // Follow-ups may be recorded late and out of order.
            if ($locked->first_contact_at === null || $data->occurredAt->lessThan($locked->first_contact_at)) {
                $locked->first_contact_at = $data->occurredAt;
            }

            if ($locked->last_contact_at === null || $data->occurredAt->greaterThan($locked->last_contact_at)) {
                $locked->last_contact_at = $data->occurredAt;
                $locked->next_follow_up_at = $data->nextFollowUpAt;
            }

            $locked->save();

            if ($locked->status === ProspectStatus::New) {
                $this->stateMachine->transition($locked, ProspectStatus::Contacted, $actor, __('prospects.reasons.first_follow_up'));
            }
        });
    }
}
