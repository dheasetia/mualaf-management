<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum ReviewStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';

    public function label(): string
    {
        return __('duplicates.review_statuses.'.$this->value);
    }
}
