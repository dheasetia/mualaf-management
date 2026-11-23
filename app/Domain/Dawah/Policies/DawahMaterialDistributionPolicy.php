<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Policies;

use App\Domain\Dawah\Models\DawahMaterialDistribution;
use App\Models\User;

/**
 * Distributions are recorded by materials.distribute and read with
 * materials.view; they are never edited or deleted.
 */
final class DawahMaterialDistributionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('materials.view') || $user->hasPermission('materials.distribute');
    }

    public function view(User $user, DawahMaterialDistribution $distribution): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('materials.distribute');
    }

    public function update(User $user, DawahMaterialDistribution $distribution): bool
    {
        return false;
    }

    public function delete(User $user, DawahMaterialDistribution $distribution): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
