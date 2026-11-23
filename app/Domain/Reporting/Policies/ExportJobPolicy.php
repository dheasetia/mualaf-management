<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Policies;

use App\Domain\Reporting\Models\ExportJob;
use App\Models\User;

/**
 * Exports are listed to holders of reports.export; each user sees and
 * downloads only their own (Exports enforces it).
 */
final class ExportJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reports.export');
    }

    public function view(User $user, ExportJob $export): bool
    {
        return $this->viewAny($user) && $export->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ExportJob $export): bool
    {
        return false;
    }

    public function delete(User $user, ExportJob $export): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
