<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Policies;

use App\Domain\Transfers\Enums\ProspectTransferStatus;
use App\Domain\Transfers\Models\ProspectTransferRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

final readonly class ProspectTransferRequestPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has() && $user->hasPermission('prospects.transfer');
    }

    public function view(User $user, ProspectTransferRequest $request): bool
    {
        return $this->viewAny($user) && in_array($this->tenantContext->id(), [$request->from_tenant_id, $request->to_tenant_id], true);
    }

    public function decide(User $user, ProspectTransferRequest $request): bool
    {
        return $request->to_tenant_id === $this->tenantContext->id()
            && $user->hasPermission('prospects.transfer')
            && $request->status === ProspectTransferStatus::Pending;
    }

    public function cancel(User $user, ProspectTransferRequest $request): bool
    {
        return $request->from_tenant_id === $this->tenantContext->id()
            && $user->hasPermission('prospects.transfer')
            && $request->status === ProspectTransferStatus::Pending;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProspectTransferRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, ProspectTransferRequest $request): bool
    {
        return false;
    }
}
