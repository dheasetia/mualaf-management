<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Services;

use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use Illuminate\Support\Collection;

/**
 * Read access to enrollments, classes and programs of the office for other
 * modules (examinations, certificates), by id.
 */
final class EnrollmentDirectory
{
    /** Class of an active or completed enrollment in the program, or false when it does not qualify. */
    public function classOfEligible(int $enrollmentId, int $programId, ?int $classId): int|false|null
    {
        $enrollment = Enrollment::query()
            ->whereKey($enrollmentId)
            ->where('program_id', $programId)
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Active, EnrollmentStatus::Paused, EnrollmentStatus::Completed])
            ->first();

        return $enrollment === null ? false : $enrollment->class_id;
    }

    public function classOf(int $enrollmentId): ?int
    {
        $classId = Enrollment::query()->whereKey($enrollmentId)->value('class_id');

        return $classId === null ? null : (int) $classId;
    }

    public function programOfClass(int $classId): ?int
    {
        $programId = GuidanceClass::query()->whereKey($classId)->value('program_id');

        return $programId === null ? null : (int) $programId;
    }

    public function programExists(int $programId): bool
    {
        return GuidanceProgram::query()->whereKey($programId)->exists();
    }

    /**
     * Enrollments that may sit an exam: id => convert id.
     *
     * @return Collection<int, int>
     */
    public function eligible(int $programId, ?int $classId): Collection
    {
        return Enrollment::query()
            ->where('program_id', $programId)
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Active, EnrollmentStatus::Paused, EnrollmentStatus::Completed])
            ->pluck('convert_id', 'id')
            ->map(fn (mixed $id): int => (int) $id);
    }

    public function convertOf(int $enrollmentId): ?int
    {
        $convertId = Enrollment::query()->whereKey($enrollmentId)->value('convert_id');

        return $convertId === null ? null : (int) $convertId;
    }

    public function recordFinalScore(int $enrollmentId, string $score): void
    {
        Enrollment::query()->whereKey($enrollmentId)->update(['final_score' => $score, 'updated_at' => now()]);
    }
}
