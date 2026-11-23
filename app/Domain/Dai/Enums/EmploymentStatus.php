<?php

declare(strict_types=1);

namespace App\Domain\Dai\Enums;

enum EmploymentStatus: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Volunteer = 'volunteer';
    case Contract = 'contract';

    public function label(): string
    {
        return __('dai.employment_statuses.'.$this->value);
    }
}
