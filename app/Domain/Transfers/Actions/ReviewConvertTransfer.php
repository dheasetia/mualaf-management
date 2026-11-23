<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Events\TransferApproved;
use App\Domain\Transfers\Exceptions\TransferRuleViolation;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Domain\Transfers\Services\TransferTimeline;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * The convert's current office takes the request into review, approves it or
 * rejects it with a reason (converts.review_transfer). Escalated requests can
 * still be decided here until Central has its own screen.
 */
final readonly class ReviewConvertTransfer
{
    public function __construct(private TransferTimeline $timeline, private TenantContext $tenantContext) {}

    public function startReview(ConvertTransferRequest $request, User $actor): ConvertTransferRequest
    {
        return $this->decide($request, TransferStatus::UnderReview, $actor, null, 'transfer.reviewed');
    }

    public function approve(ConvertTransferRequest $request, User $actor, ?string $note = null): ConvertTransferRequest
    {
        return $this->decide($request, TransferStatus::Approved, $actor, $note, 'transfer.approved');
    }

    public function reject(ConvertTransferRequest $request, User $actor, ?string $note): ConvertTransferRequest
    {
        if (blank($note)) {
            throw TransferRuleViolation::noteRequired();
        }

        return $this->decide($request, TransferStatus::Rejected, $actor, $note, 'transfer.rejected');
    }

    private function decide(ConvertTransferRequest $request, TransferStatus $target, User $actor, ?string $note, string $auditAction): ConvertTransferRequest
    {
        if (! $actor->hasPermission('converts.review_transfer')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($request, $target, $actor, $note, $auditAction): ConvertTransferRequest {
            $locked = ConvertTransferRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->from_tenant_id !== $this->tenantContext->requireId()) {
                throw TransferRuleViolation::notYourSide();
            }

            if ($target !== TransferStatus::UnderReview) {
                $locked->reviewed_by = $actor->id;
                $locked->reviewed_at = CarbonImmutable::now();
                $locked->review_note = blank($note) ? null : trim((string) $note);
            }

            $this->timeline->move($locked, $target, $actor, $auditAction, array_filter(['note' => $locked->review_note]));

            if ($target === TransferStatus::Approved) {
                TransferApproved::dispatch($locked->id, $locked->from_tenant_id, $locked->to_tenant_id);
            }

            return $locked;
        });
    }
}
