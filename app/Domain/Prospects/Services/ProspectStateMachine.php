<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Models\ProspectStatusHistory;
use App\Models\User;

/**
 * Applies a status change with its history row and audit entry. Callers run
 * it inside their transaction on a locked prospect.
 */
final readonly class ProspectStateMachine
{
    public function __construct(private AuditLogger $audit) {}

    public function transition(Prospect $prospect, ProspectStatus $target, User $actor, ?string $reason): void
    {
        $from = $prospect->status;

        if (! $from->canTransitionTo($target)) {
            throw ProspectRuleViolation::invalidTransition($from, $target);
        }

        $prospect->status = $target;
        $prospect->save();

        $this->recordHistory($prospect, $from, $target, $actor, $reason);
        $this->audit->record(
            'prospects.status_changed',
            $prospect,
            oldValues: ['status' => $from->value],
            newValues: array_filter(['status' => $target->value, 'reason' => $reason]),
            actor: $actor,
        );
    }

    public function recordHistory(Prospect $prospect, ?ProspectStatus $from, ProspectStatus $to, User $actor, ?string $reason): void
    {
        $history = new ProspectStatusHistory;
        $history->prospect_id = $prospect->id;
        $history->from_status = $from;
        $history->to_status = $to;
        $history->reason = $reason;
        $history->changed_by = $actor->id;
        $history->save();
    }
}
