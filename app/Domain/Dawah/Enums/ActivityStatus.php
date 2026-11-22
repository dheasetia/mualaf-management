<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Enums;

enum ActivityStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';

    /** Only draft and rejected activities may be changed by the da'i (08 §8.1). */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }

    public function label(): string
    {
        return __('dawah.activity_statuses.'.$this->value);
    }
}
