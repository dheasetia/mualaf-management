<?php

declare(strict_types=1);

namespace App\Domain\Transport\Enums;

enum VehicleType: string
{
    case Car = 'car';
    case Van = 'van';
    case Minibus = 'minibus';
    case Bus = 'bus';

    public function label(): string
    {
        return __('transport.vehicle_types.'.$this->value);
    }
}
