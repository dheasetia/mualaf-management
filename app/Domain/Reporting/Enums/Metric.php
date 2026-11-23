<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

/**
 * Daily statistics (11 §11.8). Flows count what happened that day; snapshots
 * describe the state at the end of that day.
 */
enum Metric: string
{
    case ShahadahVerified = 'shahadah_verified';
    case ProspectsCreated = 'prospects_created';
    case FollowUpsRecorded = 'follow_ups_recorded';
    case EnrollmentsCompleted = 'enrollments_completed';
    case CertificatesIssued = 'certificates_issued';
    case PlansCompleted = 'plans_completed';
    case SubmissionsCompleted = 'submissions_completed';
    case ConvertsActive = 'converts_active';
    case ProspectsOpen = 'prospects_open';
    case FollowUpsOverdue = 'follow_ups_overdue';

    public function isSnapshot(): bool
    {
        return in_array($this, [self::ConvertsActive, self::ProspectsOpen, self::FollowUpsOverdue], true);
    }

    public function label(): string
    {
        return __('reports.metrics.'.$this->value);
    }
}
