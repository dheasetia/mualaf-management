<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Enums;

enum MaterialStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Retired = 'retired';

    public function label(): string
    {
        return __('dawah.material_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Approved => 'success',
            self::Retired => 'danger',
        };
    }
}
