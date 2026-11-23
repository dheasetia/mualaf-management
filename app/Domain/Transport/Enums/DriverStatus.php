<?php

declare(strict_types=1);

namespace App\Domain\Transport\Enums;

enum DriverStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('transport.driver_statuses.'.$this->value);
    }
}
