<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum ChecklistStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('international.checklist_statuses.'.$this->value);
    }
}
