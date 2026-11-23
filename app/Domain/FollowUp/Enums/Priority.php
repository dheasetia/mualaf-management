<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum Priority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return __('followup.priorities.'.$this->value);
    }
}
