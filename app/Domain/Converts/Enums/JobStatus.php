<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

enum JobStatus: string
{
    case Employed = 'employed';
    case Ended = 'ended';
    case Unknown = 'unknown';

    public function label(): string
    {
        return __('converts.job_statuses.'.$this->value);
    }
}
