<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Actions;

use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Models\User;

/**
 * Hands an open prospect to another primary da'i (prospects.assign_dai).
 */
final readonly class AssignProspectDai
{
    public function __construct(private DaiAssignmentService $assignments) {}

    public function handle(Prospect $prospect, int $daiId, User $actor, ?string $reason = null): Prospect
    {
        if ($prospect->status->isTerminal()) {
            throw ProspectRuleViolation::closed();
        }

        $this->assignments->assignPrimary($prospect, $daiId, $actor, $reason);

        return $prospect->refresh();
    }
}
