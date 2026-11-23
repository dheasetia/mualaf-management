<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Enums;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case SupervisorReview = 'supervisor_review';
    case NeedsRevision = 'needs_revision';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case ReportSubmitted = 'report_submitted';
    case Verified = 'verified';
    case Cancelled = 'cancelled';

    /**
     * 12-transactions-state-machines §12.5 (rencana dakwah).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::SupervisorReview, self::Cancelled],
            self::SupervisorReview => [self::NeedsRevision, self::Approved, self::Rejected, self::Cancelled],
            self::NeedsRevision => [self::Submitted, self::Cancelled],
            self::Approved => [self::Scheduled, self::Cancelled],
            self::Scheduled => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed => [self::ReportSubmitted],
            self::ReportSubmitted => [self::Verified, self::Completed],
            self::Verified, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** The proposer may still edit the plan. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::NeedsRevision], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::NeedsRevision => 'gray',
            self::Submitted, self::SupervisorReview, self::ReportSubmitted => 'warning',
            self::Approved, self::Scheduled, self::InProgress, self::Completed => 'info',
            self::Verified => 'success',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    public function label(): string
    {
        return __('dawah.plan_statuses.'.$this->value);
    }
}
