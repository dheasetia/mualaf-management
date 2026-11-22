<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Services;

use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Prospects\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Which prospects of the office in context a user may see
 * (03-tenancy-authorization §3.5, layer 3): everyone with prospects.view,
 * otherwise (prospects.view_own) only those their da'i record is assigned to.
 */
final readonly class ProspectVisibility
{
    public function __construct(private DaiDirectory $directory, private DaiAssignmentService $assignments) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('prospects.view')) {
            return $query;
        }

        $daiId = $user->hasPermission('prospects.view_own') ? $this->directory->daiIdForUser($user) : null;

        if ($daiId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $this->assignments->assignedSubjectIds('prospect', $daiId));
    }

    public function canSee(User $user, Prospect $prospect): bool
    {
        if ($user->hasPermission('prospects.view')) {
            return true;
        }

        $daiId = $user->hasPermission('prospects.view_own') ? $this->directory->daiIdForUser($user) : null;

        return $daiId !== null && $this->assignments->isAssigned($prospect, $daiId);
    }
}
