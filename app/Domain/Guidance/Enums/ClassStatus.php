<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Enums;

enum ClassStatus: string
{
    case Planned = 'planned';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [self::Open, self::Cancelled],
            self::Open => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    /** Classes that take enrollments. */
    public function acceptsEnrollments(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }

    public function label(): string
    {
        return __('guidance.class_statuses.'.$this->value);
    }
}
