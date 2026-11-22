<?php

declare(strict_types=1);

namespace App\Domain\Converts\Contracts;

use App\Domain\Converts\Models\Convert;
use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Converts a teacher may see (converts.view_class): those enrolled in the
 * classes they teach. Implemented by the Guidance module, so Converts does
 * not depend on it.
 */
interface TeacherRoster
{
    /** Query of convert ids for whereIn(). */
    public function convertIds(User $teacher): Builder;

    public function teaches(User $teacher, Convert $convert): bool;
}
