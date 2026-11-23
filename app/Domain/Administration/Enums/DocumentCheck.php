<?php

declare(strict_types=1);

namespace App\Domain\Administration\Enums;

enum DocumentCheck: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('submissions.document_checks.'.$this->value);
    }
}
