<?php

declare(strict_types=1);

namespace App\Domain\Dai\Enums;

enum LanguageProficiency: string
{
    case Native = 'native';
    case Fluent = 'fluent';
    case Working = 'working';
    case Basic = 'basic';

    public function label(): string
    {
        return __('dai.proficiencies.'.$this->value);
    }
}
