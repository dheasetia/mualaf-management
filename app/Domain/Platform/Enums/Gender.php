<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return __('platform.genders.'.$this->value);
    }
}
