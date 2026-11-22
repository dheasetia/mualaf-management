<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum ShahadahRecordType: string
{
    case First = 'first';
    case RenewalCeremony = 'renewal_ceremony';

    public function label(): string
    {
        return __('registration.record_types.'.$this->value);
    }
}
