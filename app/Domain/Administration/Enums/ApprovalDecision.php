<?php

declare(strict_types=1);

namespace App\Domain\Administration\Enums;

enum ApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';

    public function label(): string
    {
        return __('submissions.decisions.'.$this->value);
    }
}
