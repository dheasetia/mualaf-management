<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Prospects\Services\ProspectHandover;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Enums\ProspectTransferStatus;
use App\Domain\Transfers\Exceptions\TransferRuleViolation;
use App\Domain\Transfers\Models\ProspectTransferRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Prospect transfer (07 §7.5): the current office sends the prospect (for
 * example when they move city), the new office approves with a primary da'i
 * and the prospect moves with its status and history; or the new office
 * rejects with a reason, or the sender cancels. prospects.transfer on both sides.
 */
final readonly class ProspectTransfers
{
    public function __construct(
        private ProspectHandover $handover,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
    ) {}

    public function request(int $prospectId, int $toTenantId, string $reason, User $actor): ProspectTransferRequest
    {
        $this->authorize($actor);
        $fromTenantId = $this->tenantContext->requireId();

        $destination = Tenant::query()->whereKey($toTenantId)->where('status', TenantStatus::Active)->exists();

        if ($toTenantId === $fromTenantId || ! $destination) {
            throw TransferRuleViolation::invalidDestination();
        }

        try {
            return DB::transaction(function () use ($prospectId, $toTenantId, $fromTenantId, $reason, $actor): ProspectTransferRequest {
                $this->handover->lockOpen($prospectId);

                $request = new ProspectTransferRequest;
                $request->prospect_id = $prospectId;
                $request->from_tenant_id = $fromTenantId;
                $request->to_tenant_id = $toTenantId;
                $request->requested_by = $actor->id;
                $request->reason = trim($reason);
                $request->status = ProspectTransferStatus::Pending;
                $request->save();

                $this->record('prospect_transfer.requested', $request, $actor);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw TransferRuleViolation::alreadyActive();
        }
    }

    public function approve(ProspectTransferRequest $request, int $newPrimaryDaiId, User $actor): ProspectTransferRequest
    {
        return $this->decide($request, $actor, function (ProspectTransferRequest $locked) use ($newPrimaryDaiId, $actor): void {
            if ($locked->to_tenant_id !== $this->tenantContext->requireId()) {
                throw TransferRuleViolation::notYourSide();
            }

            $this->handover->receive($locked->prospect_id, $locked->from_tenant_id, $newPrimaryDaiId, $actor, $locked->reason);

            $locked->status = ProspectTransferStatus::Completed;
            $locked->new_primary_dai_id = $newPrimaryDaiId;
            $locked->completed_at = CarbonImmutable::now();
            $this->record('prospect_transfer.completed', $locked, $actor);
        });
    }

    public function reject(ProspectTransferRequest $request, User $actor, ?string $note): ProspectTransferRequest
    {
        if (blank($note)) {
            throw TransferRuleViolation::noteRequired();
        }

        return $this->decide($request, $actor, function (ProspectTransferRequest $locked) use ($note, $actor): void {
            if ($locked->to_tenant_id !== $this->tenantContext->requireId()) {
                throw TransferRuleViolation::notYourSide();
            }

            $locked->status = ProspectTransferStatus::Rejected;
            $locked->review_note = trim((string) $note);
            $this->record('prospect_transfer.rejected', $locked, $actor);
        });
    }

    public function cancel(ProspectTransferRequest $request, User $actor): ProspectTransferRequest
    {
        return $this->decide($request, $actor, function (ProspectTransferRequest $locked) use ($actor): void {
            if ($locked->from_tenant_id !== $this->tenantContext->requireId()) {
                throw TransferRuleViolation::notYourSide();
            }

            $locked->status = ProspectTransferStatus::Cancelled;
            $this->record('prospect_transfer.cancelled', $locked, $actor);
        });
    }

    /**
     * @param  \Closure(ProspectTransferRequest): void  $change
     */
    private function decide(ProspectTransferRequest $request, User $actor, \Closure $change): ProspectTransferRequest
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($request, $actor, $change): ProspectTransferRequest {
            $locked = ProspectTransferRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== ProspectTransferStatus::Pending) {
                throw TransferRuleViolation::invalidTransition($locked->status->label(), '—');
            }

            $locked->reviewed_by = $actor->id;
            $locked->reviewed_at = CarbonImmutable::now();
            $change($locked);
            $locked->save();

            return $locked;
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('prospects.transfer')) {
            throw new AuthorizationException;
        }
    }

    private function record(string $action, ProspectTransferRequest $request, User $actor): void
    {
        $this->audit->record($action, newValues: [
            'prospect_transfer_id' => $request->id,
            'prospect_id' => $request->prospect_id,
            'from_tenant_id' => $request->from_tenant_id,
            'to_tenant_id' => $request->to_tenant_id,
            'status' => $request->status->value,
        ], actor: $actor);
    }
}
