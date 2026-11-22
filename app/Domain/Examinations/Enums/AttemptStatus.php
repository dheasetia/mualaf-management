<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Enums;

enum AttemptStatus: string
{
    case Scheduled = 'scheduled';
    case Absent = 'absent';
    case InProgress = 'in_progress';
    case Graded = 'graded';
    case Finalized = 'finalized';

    public function label(): string
    {
        return __('exams.attempt_statuses.'.$this->value);
    }
}
