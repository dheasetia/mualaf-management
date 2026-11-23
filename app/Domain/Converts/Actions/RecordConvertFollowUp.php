<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Converts\Exceptions\ConvertRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Actions\RecordFollowUp;
use App\Domain\Dai\Data\FollowUpData;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Dai\Services\DaiDirectory;
use App\Models\User;

/**
 * A da'i follow-up of a convert, by the acting user's da'i record. Holders of
 * converts.view_own only for converts assigned to them.
 */
final readonly class RecordConvertFollowUp
{
    public function __construct(
        private RecordFollowUp $recordFollowUp,
        private DaiDirectory $directory,
        private DaiAssignmentService $assignments,
    ) {}

    public function handle(Convert $convert, FollowUpData $data, User $actor): void
    {
        if ($convert->status->isTerminal()) {
            throw ConvertRuleViolation::closed();
        }

        $daiId = $this->directory->daiIdForUser($actor) ?? throw DaiRuleViolation::notADai();

        if (! $actor->hasPermission('converts.view') && ! $this->assignments->isAssigned($convert, $daiId)) {
            throw DaiRuleViolation::assigneeUnavailable();
        }

        $this->recordFollowUp->handle($convert, $daiId, $data, $actor);
    }
}
