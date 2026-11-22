<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Actions;

use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Services\ProspectStateMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manual status change. `converted` needs the shahadah workflow and
 * `transferred` a prospect transfer, so neither is accepted here.
 */
final readonly class ChangeProspectStatus
{
    /** Leaving active work needs a reason, for later review. */
    private const array NEEDS_REASON = [ProspectStatus::NotInterested, ProspectStatus::LostContact, ProspectStatus::Closed];

    public function __construct(private ProspectStateMachine $stateMachine) {}

    public function handle(Prospect $prospect, ProspectStatus $target, User $actor, ?string $reason = null): Prospect
    {
        $reason = blank($reason) ? null : trim((string) $reason);

        if ($reason === null && in_array($target, self::NEEDS_REASON, true)) {
            throw ProspectRuleViolation::reasonRequired();
        }

        return DB::transaction(function () use ($prospect, $target, $actor, $reason): Prospect {
            $locked = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);

            if (! in_array($target, $locked->status->manualTransitions(), true)) {
                throw ProspectRuleViolation::invalidTransition($locked->status, $target);
            }

            $this->stateMachine->transition($locked, $target, $actor, $reason);

            return $locked;
        });
    }
}
