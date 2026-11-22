<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Enums;

/**
 * 12-transactions-state-machines §12.5 "Transfer convert".
 */
enum TransferStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Escalated = 'escalated';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::UnderReview, self::Approved, self::Rejected, self::Cancelled, self::Escalated],
            self::UnderReview => [self::Approved, self::Rejected, self::Cancelled, self::Escalated],
            self::Escalated => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Accepted, self::Cancelled],
            self::Accepted => [self::Completed],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Statuses counted by active_convert_key. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    public function label(): string
    {
        return __('transfers.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::UnderReview, self::Escalated => 'warning',
            self::Approved, self::Accepted => 'info',
            self::Completed => 'success',
            self::Rejected, self::Cancelled => 'gray',
        };
    }
}
