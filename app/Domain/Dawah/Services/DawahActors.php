<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Services;

use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Models\User;

/**
 * Who a user is for a plan: the proposing or responsible da'i.
 */
final readonly class DawahActors
{
    public function __construct(private DaiDirectory $directory) {}

    public function daiId(User $user): ?int
    {
        return $this->directory->daiIdForUser($user);
    }

    public function isPlanDai(User $user, DawahActivityPlan $plan): bool
    {
        $daiId = $this->daiId($user);

        return $daiId !== null && in_array($daiId, [$plan->proposed_by_dai_id, $plan->responsible_dai_id], true);
    }

    public function isResponsible(User $user, DawahActivityPlan $plan): bool
    {
        $daiId = $this->daiId($user);

        return $daiId !== null && $daiId === $plan->responsible_dai_id;
    }

    /** Office staff who run approved plans: the responsible da'i or a plan approver. */
    public function runsPlan(User $user, DawahActivityPlan $plan): bool
    {
        return ($this->isResponsible($user, $plan) && $user->hasPermission('dawah.plans.submit')) || $user->hasPermission('dawah.plans.approve');
    }
}
