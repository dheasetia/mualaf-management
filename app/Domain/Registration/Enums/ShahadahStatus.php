<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum ShahadahStatus: string
{
    case Recorded = 'recorded';
    case Verified = 'verified';
    case Voided = 'voided';

    public function label(): string
    {
        return __('registration.shahadah_statuses.'.$this->value);
    }
}
