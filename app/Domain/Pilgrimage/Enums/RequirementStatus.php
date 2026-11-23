<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Enums;

enum RequirementStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Waived = 'waived';

    public function label(): string
    {
        return __('pilgrimage.requirement_statuses.'.$this->value);
    }
}
