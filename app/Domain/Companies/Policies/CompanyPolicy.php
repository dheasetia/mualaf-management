<?php

declare(strict_types=1);

namespace App\Domain\Companies\Policies;

use App\Domain\Companies\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Offices read the national directory and their own companies, and manage
 * their own (converts.update or prospects.update). Central manages the
 * national directory (master.manage). Companies are never deleted except by
 * a merge on promotion.
 */
final readonly class CompanyPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has()
            ? $user->hasPermission('converts.view') || $user->hasPermission('prospects.view') || $user->hasPermission('prospects.view_own')
            : $user->hasPermission('master.manage');
    }

    public function view(User $user, Company $company): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->mayManage($user);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->mayManage($user) && $company->tenant_id === $this->tenantContext->id();
    }

    public function promote(User $user, Company $company): bool
    {
        return ! $this->tenantContext->has() && $user->hasPermission('master.manage') && ! $company->isNational();
    }

    public function delete(User $user, Company $company): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function mayManage(User $user): bool
    {
        return $this->tenantContext->has()
            ? $user->hasPermission('converts.update') || $user->hasPermission('prospects.update')
            : $user->hasPermission('master.manage');
    }
}
