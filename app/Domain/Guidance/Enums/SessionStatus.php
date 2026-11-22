<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Held = 'held';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('guidance.session_statuses.'.$this->value);
    }
}
