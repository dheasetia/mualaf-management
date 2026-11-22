<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Services;

use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use Carbon\CarbonImmutable;
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

    /**
     * Completion facts of a completed enrollment, or null.
     *
     * @return array{convert_id: int, program_id: int, program_name_ar: string, program_name_en: ?string, attendance: ?string, minimum_attendance: string, completed_at: CarbonImmutable, certificate_template_id: ?int}|null
     */
    public function completion(int $enrollmentId): ?array
    {
        $enrollment = Enrollment::query()->with('program')->whereKey($enrollmentId)->where('status', EnrollmentStatus::Completed)->first();

        if ($enrollment === null || $enrollment->completed_at === null) {
            return null;
        }

        return [
            'convert_id' => $enrollment->convert_id,
            'program_id' => $enrollment->program_id,
            'program_name_ar' => $enrollment->program->name_ar,
            'program_name_en' => $enrollment->program->name_en,
            'attendance' => $enrollment->attendance_percentage,
            'minimum_attendance' => $enrollment->program->minimum_attendance_percentage,
            'completed_at' => $enrollment->completed_at,
            'certificate_template_id' => $enrollment->program->getAttribute('certificate_template_id') === null ? null : (int) $enrollment->program->getAttribute('certificate_template_id'),
        ];
    }

    /**
     * Completed enrollments of the office for pickers: id => convert id.
     *
     * @return Collection<int, int>
     */
    public function completed(): Collection
    {
        return Enrollment::query()->where('status', EnrollmentStatus::Completed)->pluck('convert_id', 'id')->map(fn (mixed $id): int => (int) $id);
    }

    public function recordFinalScore(int $enrollmentId, string $score): void
    {
        Enrollment::query()->whereKey($enrollmentId)->update(['final_score' => $score, 'updated_at' => now()]);
    }
}
