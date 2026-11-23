<?php

declare(strict_types=1);

namespace App\Domain\Transport\Enums;

enum PickupStatus: string
{
    case Requested = 'requested';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case NoShow = 'no_show';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Assigned, self::Cancelled],
            self::Assigned => [self::Requested, self::PickedUp, self::NoShow, self::Cancelled],
            self::PickedUp => [self::Completed],
            self::NoShow, self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return __('transport.pickup_statuses.'.$this->value);
    }
}
