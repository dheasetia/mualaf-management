<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum VoidRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('registration.void_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'danger',
            self::Rejected => 'gray',
        };
    }
}
