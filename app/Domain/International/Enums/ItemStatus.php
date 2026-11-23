<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum ItemStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return __('international.item_statuses.'.$this->value);
    }
}
