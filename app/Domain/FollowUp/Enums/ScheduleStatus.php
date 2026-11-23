<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum ScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Missed = 'missed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return __('followup.schedule_statuses.'.$this->value);
    }
}
