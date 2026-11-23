<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Enums;

enum ExamType: string
{
    case Evaluation = 'evaluation';
    case Practical = 'practical';
    case Monthly = 'monthly';
    case Final = 'final';
    case Remedial = 'remedial';

    public function label(): string
    {
        return __('exams.types.'.$this->value);
    }
}
