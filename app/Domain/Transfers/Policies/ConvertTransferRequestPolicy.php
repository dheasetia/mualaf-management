<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Policies;

use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

final readonly class ConvertTransferRequestPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has()
            && ($user->hasPermission('converts.transfer') || $user->hasPermission('converts.review_transfer'));
    }

    public function view(User $user, ConvertTransferRequest $request): bool
    {
        return $this->viewAny($user) && in_array($this->tenantContext->id(), [$request->from_tenant_id, $request->to_tenant_id], true);
    }

    /** Old office: take into review, approve or reject. */
    public function review(User $user, ConvertTransferRequest $request): bool
    {
        return $request->from_tenant_id === $this->tenantContext->id()
            && $user->hasPermission('converts.review_transfer')
            && in_array($request->status, [TransferStatus::Pending, TransferStatus::UnderReview, TransferStatus::Escalated], true);
    }

    /** New office: accept an approved transfer with a da'i. */
    public function accept(User $user, ConvertTransferRequest $request): bool
    {
        return $request->to_tenant_id === $this->tenantContext->id()
            && $user->hasPermission('converts.transfer')
            && $request->status === TransferStatus::Approved;
    }

    public function cancel(User $user, ConvertTransferRequest $request): bool
    {
        return $request->to_tenant_id === $this->tenantContext->id()
            && $user->hasPermission('converts.transfer')
            && $request->status->canTransitionTo(TransferStatus::Cancelled);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ConvertTransferRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, ConvertTransferRequest $request): bool
    {
        return false;
    }
}
