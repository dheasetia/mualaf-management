<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Policies;

use App\Domain\Dawah\Models\DaiDailyActivity;
use App\Domain\Dawah\Services\DawahActors;
use App\Models\User;

/**
 * Da'i see and edit their own activities; Head of Dawah sees all to verify.
 */
final readonly class DaiDailyActivityPolicy
{
    public function __construct(private DawahActors $actors) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('dawah.activities.verify') || ($user->hasPermission('dawah.activities.create') && $this->actors->daiId($user) !== null);
    }

    public function view(User $user, DaiDailyActivity $activity): bool
    {
        return $user->hasPermission('dawah.activities.verify') || $this->isOwn($user, $activity);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('dawah.activities.create') && $this->actors->daiId($user) !== null;
    }

    public function update(User $user, DaiDailyActivity $activity): bool
    {
        return $user->hasPermission('dawah.activities.create') && $this->isOwn($user, $activity) && $activity->status->isEditable();
    }

    public function delete(User $user, DaiDailyActivity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function isOwn(User $user, DaiDailyActivity $activity): bool
    {
        return $activity->dai_id === $this->actors->daiId($user);
    }
}
