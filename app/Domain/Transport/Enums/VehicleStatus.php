<?php

declare(strict_types=1);

namespace App\Domain\Transport\Enums;

enum VehicleStatus: string
{
    case Available = 'available';
    case InMaintenance = 'in_maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return __('transport.vehicle_statuses.'.$this->value);
    }
}
