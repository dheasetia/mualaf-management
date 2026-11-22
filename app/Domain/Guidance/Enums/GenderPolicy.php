<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Enums;

enum GenderPolicy: string
{
    case Male = 'male';
    case Female = 'female';
    case Mixed = 'mixed';

    public function label(): string
    {
        return __('guidance.gender_policies.'.$this->value);
    }
}
