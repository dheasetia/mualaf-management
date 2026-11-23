<?php

declare(strict_types=1);

namespace App\Domain\International\Policies;

use App\Domain\International\Models\ExternalIslamicOrganization;
use App\Domain\International\Models\InternationalReferral;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Referrals: international.referrals.*; checklists: followups.view; the
 * organization directory: international.organizations.manage (Central).
 * Steps are checked by the International actions; nothing is deleted.
 */
final class InternationalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('international.referrals.create') || $user->hasPermission('international.referrals.send')
            || $user->hasPermission('followups.view') || $user->hasPermission('international.organizations.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return match (true) {
            $model instanceof InternationalReferral => $user->hasPermission('international.referrals.create') || $user->hasPermission('international.referrals.send'),
            $model instanceof PreDepartureChecklist => $user->hasPermission('followups.view'),
            $model instanceof ExternalIslamicOrganization => $user->hasPermission('international.organizations.manage'),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('international.organizations.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $model instanceof ExternalIslamicOrganization && $user->hasPermission('international.organizations.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
