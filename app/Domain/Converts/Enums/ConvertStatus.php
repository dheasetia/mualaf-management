<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

/**
 * Administrative record status only, never a statement about faith (06 §6.8).
 */
enum ConvertStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deceased = 'deceased';
    case RecordClosed = 'record_closed';

    /**
     * 12-transactions-state-machines §12.5: deceased and record_closed are
     * final; corrections go through Central.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Inactive, self::Deceased, self::RecordClosed],
            self::Inactive => [self::Active, self::Deceased, self::RecordClosed],
            self::Deceased, self::RecordClosed => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return __('converts.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Deceased, self::RecordClosed => 'gray',
        };
    }
}
