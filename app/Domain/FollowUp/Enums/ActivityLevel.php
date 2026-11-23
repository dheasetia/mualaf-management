<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum ActivityLevel: string
{
    case Regular = 'regular';
    case Irregular = 'irregular';
    case Unknown = 'unknown';

    public function label(): string
    {
        return __('followup.activity_levels.'.$this->value);
    }
}
