<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Services\ProspectRules;
use App\Domain\Prospects\Services\ProspectStateMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registers a prospect (status new) in the office in context with a primary da'i.
 *
 * Holders of prospects.assign_dai choose the da'i; anyone else (a da'i
 * registering their own contact) becomes the primary da'i themselves.
 */
final readonly class CreateProspect
{
    public function __construct(
        private ProspectRules $rules,
        private ProspectStateMachine $stateMachine,
        private DaiAssignmentService $assignments,
        private DaiDirectory $directory,
        private AuditLogger $audit,
    ) {}

    public function handle(ProspectData $data, User $actor): Prospect
    {
        $primaryDaiId = $actor->hasPermission('prospects.assign_dai')
            ? $data->primaryDaiId
            : $this->directory->daiIdForUser($actor);

        if ($primaryDaiId === null) {
            throw ProspectRuleViolation::primaryDaiRequired();
        }

        $this->rules->checkPossibleDuplicate($data);

        return DB::transaction(function () use ($data, $actor, $primaryDaiId): Prospect {
            $prospect = new Prospect;
            $this->rules->fill($prospect, $data);
            $prospect->primary_dai_id = $primaryDaiId;
            $prospect->status = ProspectStatus::New;
            $prospect->created_by = $actor->id;
            $prospect->save();

            $this->stateMachine->recordHistory($prospect, null, ProspectStatus::New, $actor, null);
            $this->assignments->assignPrimary($prospect, $primaryDaiId, $actor);

            $this->audit->record('prospects.created', $prospect, newValues: $prospect->getAttributes(), actor: $actor);

            return $prospect;
        });
    }
}
