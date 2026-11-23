<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Excused = 'excused';
    case Late = 'late';

    public function label(): string
    {
        return __('guidance.attendance_statuses.'.$this->value);
    }
}
