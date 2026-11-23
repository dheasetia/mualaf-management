<?php

declare(strict_types=1);

namespace App\Domain\Converts\Policies;

use App\Domain\Converts\Enums\ProfileAccess;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Converts are created only by the shahadah workflow (step 11) and never
 * deleted. Opening or changing a record needs the full profile
 * (see ConvertAccess); closed records (deceased, record_closed) are read-only.
 */
final readonly class ConvertPolicy
{
    public function __construct(private TenantContext $tenantContext, private ConvertAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has()
            && ($user->hasPermission('converts.view') || $user->hasPermission('converts.view_own'));
    }

    public function view(User $user, Convert $convert): bool
    {
        return $this->inOffice($convert) && $this->access->profile($user, $convert) !== ProfileAccess::Hidden;
    }

    public function update(User $user, Convert $convert): bool
    {
        return $this->openWithFullProfile($user, $convert) && $user->hasPermission('converts.update');
    }

    public function setDisclosure(User $user, Convert $convert): bool
    {
        return $this->full($user, $convert) && $user->hasPermission('converts.set_disclosure');
    }

    public function changeStatus(User $user, Convert $convert): bool
    {
        return $this->openWithFullProfile($user, $convert) && $user->hasPermission('converts.change_status');
    }

    public function assignDai(User $user, Convert $convert): bool
    {
        return $this->openWithFullProfile($user, $convert) && $user->hasPermission('converts.assign_dai');
    }

    public function manageContacts(User $user, Convert $convert): bool
    {
        return $this->update($user, $convert);
    }

    public function viewContacts(User $user, Convert $convert): bool
    {
        return $this->full($user, $convert);
    }

    public function revealIdentity(User $user, Convert $convert): bool
    {
        return $this->full($user, $convert) && $user->hasPermission('identity.reveal');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Convert $convert): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function inOffice(Convert $convert): bool
    {
        return $this->tenantContext->has() && $convert->current_tenant_id === $this->tenantContext->id();
    }

    private function full(User $user, Convert $convert): bool
    {
        return $this->inOffice($convert) && $this->access->profile($user, $convert) === ProfileAccess::Full;
    }

    private function openWithFullProfile(User $user, Convert $convert): bool
    {
        return ! $convert->status->isTerminal() && $this->full($user, $convert);
    }
}
