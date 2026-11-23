<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Services;

use App\Domain\Guidance\Models\ClassTeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Which classes a user teaches (03-tenancy-authorization §3.5 layer 3:
 * teachers act only in the classes assigned to them).
 */
final class TeacherAssignments
{
    public function teaches(User $user, int $classId): bool
    {
        return ClassTeacherAssignment::query()
            ->where('class_id', $classId)
            ->where('user_id', $user->id)
            ->whereNull('released_at')
            ->exists();
    }

    /** Ids of classes the user currently teaches. */
    public function classIds(User $user): QueryBuilder
    {
        return ClassTeacherAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('released_at')
            ->select('class_id')
            ->toBase();
    }

    /**
     * Teachers (attendance or progress recorders without class management)
     * see only their own classes; everyone else with guidance.view sees all.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function visibleClasses(Builder $query, User $user): Builder
    {
        return $this->onlyOwnClasses($user) ? $query->whereIn('id', $this->classIds($user)) : $query;
    }

    public function onlyOwnClasses(User $user): bool
    {
        return ! $user->hasPermission('guidance.classes.manage')
            && ($user->hasPermission('guidance.attendance.record') || $user->hasPermission('guidance.progress.record'));
    }
}
