<?php

declare(strict_types=1);

namespace App\Domain\Converts\Services;

use App\Domain\Converts\Contracts\TeacherRoster;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Enums\ProfileAccess;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Dai\Services\DaiDirectory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who sees which converts of the office in context, and how much of each
 * (03-tenancy-authorization §3.5 layers 3–4, 04-identity-privacy §4.6).
 *
 * - converts.view: every convert of the office; converts.view_own (da'i):
 *   those their da'i record is assigned to; converts.view_class (teacher):
 *   those enrolled in the classes they teach (TeacherRoster).
 * - restricted: full profile for the primary da'i and converts.view_restricted,
 *   name and operational minimum for others.
 * - high_confidentiality: full profile for the primary da'i and
 *   converts.view_high_confidentiality; an alias in lists for others.
 */
final readonly class ConvertAccess
{
    public function __construct(
        private DaiDirectory $directory,
        private DaiAssignmentService $assignments,
        private TeacherRoster $roster,
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function visible(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('converts.view')) {
            return $query;
        }

        $daiId = $this->ownDaiId($user);
        $teaches = $user->hasPermission('converts.view_class');

        if ($daiId === null && ! $teaches) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $visible) => $visible
            ->when($daiId !== null, fn (Builder $own) => $own->orWhereIn('id', $this->assignments->assignedSubjectIds('convert', (int) $daiId)))
            ->when($teaches, fn (Builder $class) => $class->orWhereIn('id', $this->roster->convertIds($user))));
    }

    public function canSee(User $user, Convert $convert): bool
    {
        if ($user->hasPermission('converts.view')) {
            return true;
        }

        $daiId = $this->ownDaiId($user);

        if ($daiId !== null && $this->assignments->isAssigned($convert, $daiId)) {
            return true;
        }

        return $user->hasPermission('converts.view_class') && $this->roster->teaches($user, $convert);
    }

    public function profile(User $user, Convert $convert): ProfileAccess
    {
        if (! $this->canSee($user, $convert)) {
            return ProfileAccess::Hidden;
        }

        $isPrimaryDai = $this->directory->daiIdForUser($user) === $convert->primary_dai_id;

        return match ($convert->disclosure_risk_level) {
            DisclosureLevel::Normal => ProfileAccess::Full,
            DisclosureLevel::Restricted => $isPrimaryDai || $user->hasPermission('converts.view_restricted')
                ? ProfileAccess::Full : ProfileAccess::Minimal,
            DisclosureLevel::HighConfidentiality => $isPrimaryDai || $user->hasPermission('converts.view_high_confidentiality')
                ? ProfileAccess::Full : ProfileAccess::Hidden,
        };
    }

    /** Previous religion and shahadah details (04-identity-privacy §4.5). */
    public function canSeeSensitive(User $user, Convert $convert): bool
    {
        return $this->profile($user, $convert) === ProfileAccess::Full && $user->hasPermission('converts.view_sensitive');
    }

    public function displayName(User $user, Convert $convert): string
    {
        return match ($this->profile($user, $convert)) {
            ProfileAccess::Full => $convert->full_name,
            ProfileAccess::Minimal => $convert->islamic_name ?? $convert->initials(),
            ProfileAccess::Hidden => $convert->alias(),
        };
    }

    private function ownDaiId(User $user): ?int
    {
        return $user->hasPermission('converts.view_own') ? $this->directory->daiIdForUser($user) : null;
    }
}
