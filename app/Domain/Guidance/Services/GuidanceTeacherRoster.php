<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Services;

use App\Domain\Converts\Contracts\TeacherRoster;
use App\Domain\Converts\Models\Convert;
use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Query\Builder;

final readonly class GuidanceTeacherRoster implements TeacherRoster
{
    public function __construct(private TeacherAssignments $assignments) {}

    public function convertIds(User $teacher): Builder
    {
        return Enrollment::query()
            ->whereIn('class_id', $this->assignments->classIds($teacher))
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Active, EnrollmentStatus::Paused])
            ->select('convert_id')
            ->toBase();
    }

    public function teaches(User $teacher, Convert $convert): bool
    {
        return Enrollment::query()
            ->where('convert_id', $convert->id)
            ->whereIn('class_id', $this->assignments->classIds($teacher))
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Active, EnrollmentStatus::Paused])
            ->exists();
    }
}
