<?php

declare(strict_types=1);

namespace App\Domain\Privileged\Enums;

enum PrivilegedStatus: string
{
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return __('privileged.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Rejected, self::Expired, self::Revoked => 'gray',
        };
    }
}
