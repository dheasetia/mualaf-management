<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Converts\Exceptions\ConvertRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Models\User;

final readonly class AssignConvertDai
{
    public function __construct(private DaiAssignmentService $assignments) {}

    public function handle(Convert $convert, int $daiId, User $actor, ?string $reason = null): Convert
    {
        if ($convert->status->isTerminal()) {
            throw ConvertRuleViolation::closed();
        }

        $this->assignments->assignPrimary($convert, $daiId, $actor, $reason);

        return $convert->refresh();
    }
}
