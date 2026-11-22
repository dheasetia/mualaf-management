<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Enums;

enum ResultStatus: string
{
    case Provisional = 'provisional';
    case Finalized = 'finalized';
    case Amended = 'amended';

    public function label(): string
    {
        return __('exams.result_statuses.'.$this->value);
    }
}
