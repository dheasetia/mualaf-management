<?php

declare(strict_types=1);

namespace App\Domain\Dai\Policies;

use App\Domain\Dai\Models\Dai;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Office-level permissions only (they apply inside the tenant in context);
 * the tenant scope on Dai keeps other offices' da'i out of reach.
 * Da'i are never deleted: they become inactive.
 */
final readonly class DaiPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->inOffice($user, 'dais.view');
    }

    public function view(User $user, Dai $dai): bool
    {
        return $this->inOffice($user, 'dais.view') && $dai->tenant_id === $this->tenantContext->id();
    }

    public function create(User $user): bool
    {
        return $this->inOffice($user, 'dais.create');
    }

    public function update(User $user, Dai $dai): bool
    {
        return $this->inOffice($user, 'dais.update') && $dai->tenant_id === $this->tenantContext->id();
    }

    public function changeStatus(User $user, Dai $dai): bool
    {
        return $this->update($user, $dai);
    }

    public function viewWorkload(User $user): bool
    {
        return $this->inOffice($user, 'dais.view_workload');
    }

    public function delete(User $user, Dai $dai): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function inOffice(User $user, string $permission): bool
    {
        return $this->tenantContext->has() && $user->hasPermission($permission);
    }
}
