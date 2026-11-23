<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Enums;

enum ProgramStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open, self::Cancelled],
            self::Open => [self::Closed, self::Cancelled],
            self::Closed => [self::Open, self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return __('pilgrimage.program_statuses.'.$this->value);
    }
}
