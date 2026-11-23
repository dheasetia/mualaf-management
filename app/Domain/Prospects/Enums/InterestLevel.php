<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Enums;

enum InterestLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return __('prospects.interest_levels.'.$this->value);
    }
}
