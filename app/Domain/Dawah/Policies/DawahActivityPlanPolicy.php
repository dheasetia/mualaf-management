<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Policies;

use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Models\User;

/**
 * Plans are read by dawah.plans.view and edited by their own da'i while a
 * draft or returned for revision; transitions are checked by PlanWorkflow.
 */
final readonly class DawahActivityPlanPolicy
{
    public function __construct(private DawahActors $actors) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('dawah.plans.view');
    }

    public function view(User $user, DawahActivityPlan $plan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('dawah.plans.create') && $this->actors->daiId($user) !== null;
    }

    public function update(User $user, DawahActivityPlan $plan): bool
    {
        return $user->hasPermission('dawah.plans.create') && $plan->status->isEditable() && $this->actors->isPlanDai($user, $plan);
    }

    public function delete(User $user, DawahActivityPlan $plan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
