<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum GeneratedStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('documents.generated_statuses.'.$this->value);
    }
}
