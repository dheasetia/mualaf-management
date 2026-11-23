<?php

declare(strict_types=1);

namespace App\Domain\Administration\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderVerification = 'under_verification';
    case NeedsCorrection = 'needs_correction';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * 12-transactions-state-machines §12.5 (submission).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::UnderVerification, self::Cancelled],
            self::UnderVerification => [self::NeedsCorrection, self::Approved, self::Rejected, self::Cancelled],
            self::NeedsCorrection => [self::Submitted, self::Cancelled],
            self::Approved => [self::Processing, self::Cancelled],
            self::Processing => [self::Completed],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Documents may be attached or replaced. */
    public function acceptsDocuments(): bool
    {
        return in_array($this, [self::Draft, self::NeedsCorrection], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::NeedsCorrection => 'gray',
            self::Submitted, self::UnderVerification => 'warning',
            self::Approved, self::Processing => 'info',
            self::Completed => 'success',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    public function label(): string
    {
        return __('submissions.statuses.'.$this->value);
    }
}
