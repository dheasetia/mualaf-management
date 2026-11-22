<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Policies;

use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Services\ProspectVisibility;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Office permissions plus ownership: holders of a `*_own` permission act only
 * on prospects their da'i record is assigned to. Closed, converted and
 * transferred prospects are read-only. Prospects are never deleted.
 */
final readonly class ProspectPolicy
{
    public function __construct(private TenantContext $tenantContext, private ProspectVisibility $visibility) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has()
            && ($user->hasPermission('prospects.view') || $user->hasPermission('prospects.view_own'));
    }

    public function view(User $user, Prospect $prospect): bool
    {
        return $this->inOffice($prospect) && $this->visibility->canSee($user, $prospect);
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->has() && $user->hasPermission('prospects.create');
    }

    public function update(User $user, Prospect $prospect): bool
    {
        return $this->openAndVisible($user, $prospect) && $user->hasPermission('prospects.update');
    }

    public function changeStatus(User $user, Prospect $prospect): bool
    {
        return $this->openAndVisible($user, $prospect)
            && $user->hasPermission('prospects.change_status')
            && $prospect->status->manualTransitions() !== [];
    }

    public function assignDai(User $user, Prospect $prospect): bool
    {
        return $this->openAndVisible($user, $prospect) && $user->hasPermission('prospects.assign_dai');
    }

    public function recordFollowUp(User $user, Prospect $prospect): bool
    {
        return $this->openAndVisible($user, $prospect) && $user->hasPermission('followups.create');
    }

    public function transfer(User $user, Prospect $prospect): bool
    {
        return $this->openAndVisible($user, $prospect) && $user->hasPermission('prospects.transfer');
    }

    public function viewFollowUps(User $user, Prospect $prospect): bool
    {
        return $this->view($user, $prospect) && $user->hasPermission('followups.view');
    }

    public function delete(User $user, Prospect $prospect): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function inOffice(Prospect $prospect): bool
    {
        return $this->tenantContext->has() && $prospect->tenant_id === $this->tenantContext->id();
    }

    private function openAndVisible(User $user, Prospect $prospect): bool
    {
        return ! $prospect->status->isTerminal() && $this->view($user, $prospect);
    }
}
