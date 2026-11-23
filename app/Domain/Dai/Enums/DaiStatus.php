<?php

declare(strict_types=1);

namespace App\Domain\Dai\Enums;

enum DaiStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Inactive = 'inactive';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::OnLeave, self::Inactive],
            self::OnLeave => [self::Active, self::Inactive],
            self::Inactive => [self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return __('dai.statuses.'.$this->value);
    }
}
