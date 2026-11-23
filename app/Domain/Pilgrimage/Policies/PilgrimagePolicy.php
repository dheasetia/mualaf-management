<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Policies;

use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Pilgrimage screens are read with pilgrimage.view; programs are kept by
 * pilgrimage.programs.manage while a draft or open. Steps are checked by
 * the Pilgrimage actions. Nothing is deleted.
 */
final class PilgrimagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pilgrimage.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pilgrimage.programs.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $model instanceof PilgrimageProgram && in_array($model->status, [ProgramStatus::Draft, ProgramStatus::Open], true) && $this->create($user);
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
