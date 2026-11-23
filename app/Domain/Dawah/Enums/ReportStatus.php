<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Returned = 'returned';

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    public function label(): string
    {
        return __('dawah.report_statuses.'.$this->value);
    }
}
