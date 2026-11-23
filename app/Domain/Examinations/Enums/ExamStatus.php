<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Enums;

/**
 * 12-transactions-state-machines §12.5 "Ujian".
 */
enum ExamStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Graded = 'graded';
    case PendingFinalization = 'pending_finalization';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Graded, self::Cancelled],
            self::Graded => [self::PendingFinalization, self::InProgress],
            self::PendingFinalization => [self::Finalized, self::Graded],
            self::Finalized, self::Cancelled => [],
        };
    }

    /** Scores can still be entered or changed. */
    public function acceptsGrading(): bool
    {
        return in_array($this, [self::InProgress, self::Graded], true);
    }

    public function label(): string
    {
        return __('exams.statuses.'.$this->value);
    }
}
