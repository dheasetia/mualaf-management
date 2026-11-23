<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Services;

use App\Domain\Guidance\Enums\AttendanceStatus;
use App\Domain\Guidance\Enums\SessionStatus;
use App\Domain\Guidance\Models\Attendance;
use App\Domain\Guidance\Models\Enrollment;

/**
 * 09-data-model-guidance §9.4: (present + late × weight) / (held sessions
 * recorded for the enrollment − excused) × 100. Null when nothing counts yet.
 */
final class AttendanceCalculator
{
    public function percentage(Enrollment $enrollment): ?float
    {
        $statuses = Attendance::query()
            ->join('class_sessions', 'class_sessions.id', '=', 'attendances.class_session_id')
            ->where('attendances.enrollment_id', $enrollment->id)
            ->where('class_sessions.status', SessionStatus::Held)
            ->pluck('attendances.status')
            ->map(fn (mixed $status): AttendanceStatus => $status instanceof AttendanceStatus ? $status : AttendanceStatus::from((string) $status));

        $counted = $statuses->reject(fn (AttendanceStatus $status): bool => $status === AttendanceStatus::Excused)->count();

        if ($counted === 0) {
            return null;
        }

        $lateWeight = (float) config('guidance.late_weight');
        $attended = $statuses->filter(fn (AttendanceStatus $status): bool => $status === AttendanceStatus::Present)->count()
            + $lateWeight * $statuses->filter(fn (AttendanceStatus $status): bool => $status === AttendanceStatus::Late)->count();

        return round($attended / $counted * 100, 2);
    }

    public function refresh(Enrollment $enrollment): void
    {
        $percentage = $this->percentage($enrollment);
        $enrollment->attendance_percentage = $percentage === null ? null : number_format($percentage, 2, '.', '');
        $enrollment->save();
    }
}
