<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Enums;

/**
 * 12-transactions-state-machines §12.5 "Enrollment".
 */
enum EnrollmentStatus: string
{
    case Enrolled = 'enrolled';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';
    case Transferred = 'transferred';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Enrolled => [self::Active, self::Withdrawn, self::Transferred],
            self::Active => [self::Paused, self::Completed, self::Withdrawn, self::Transferred],
            self::Paused => [self::Active, self::Withdrawn, self::Transferred],
            self::Completed, self::Withdrawn, self::Transferred => [],
        };
    }

    /** Matches the active_enrollment_key generated column. */
    public function isActive(): bool
    {
        return in_array($this, [self::Enrolled, self::Active, self::Paused], true);
    }

    public function label(): string
    {
        return __('guidance.enrollment_statuses.'.$this->value);
    }
}
