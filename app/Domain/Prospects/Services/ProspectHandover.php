<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Services;

use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * The prospect side of a prospect transfer (07 §7.5), for the Transfers module.
 */
final readonly class ProspectHandover
{
    public function __construct(
        private TenantContext $tenantContext,
        private DaiAssignmentService $assignments,
        private ProspectStateMachine $stateMachine,
    ) {}

    /** An open prospect of the office in context, locked. Returns its name. */
    public function lockOpen(int $prospectId): string
    {
        $prospect = Prospect::query()->lockForUpdate()->find($prospectId);

        if (! $prospect instanceof Prospect || $prospect->status->isTerminal()) {
            throw ProspectRuleViolation::closed();
        }

        return $prospect->full_name;
    }

    /**
     * Moves the prospect from $fromTenantId to the office in context, keeps
     * its status and history, and gives it the new office's primary da'i.
     */
    public function receive(int $prospectId, int $fromTenantId, int $newPrimaryDaiId, User $actor, string $reason): void
    {
        $toTenantId = $this->tenantContext->requireId();

        $prospect = $this->tenantContext->run($fromTenantId, function () use ($prospectId): Prospect {
            $prospect = Prospect::query()->lockForUpdate()->find($prospectId);

            if (! $prospect instanceof Prospect || $prospect->status->isTerminal()) {
                throw ProspectRuleViolation::closed();
            }

            return $prospect;
        });

        $prospect->moveToOffice($toTenantId);
        $this->assignments->endAllFor('prospect', $prospect->id, 'transferred');
        $this->assignments->assignPrimary($prospect, $newPrimaryDaiId, $actor, 'transfer');

        $status = $prospect->refresh()->status;
        $this->stateMachine->recordHistory($prospect, $status, $status, $actor, $reason);
    }

    /** @return array<int, string> open prospects of the office: id => name */
    public function openOptions(): array
    {
        return Prospect::query()
            ->whereNotIn('status', array_map(fn (ProspectStatus $status): string => $status->value, array_filter(ProspectStatus::cases(), fn (ProspectStatus $status): bool => $status->isTerminal())))
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }
}
