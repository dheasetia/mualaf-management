<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Enums;

enum AttendanceStatus: string
{
    case Invited = 'invited';
    case Confirmed = 'confirmed';
    case Attended = 'attended';
    case Absent = 'absent';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('dawah.attendance_statuses.'.$this->value);
    }
}
