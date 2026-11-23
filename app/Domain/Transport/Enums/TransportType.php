<?php

declare(strict_types=1);

namespace App\Domain\Transport\Enums;

enum TransportType: string
{
    case OfficeVehicle = 'office_vehicle';
    case Rental = 'rental';
    case Reimbursement = 'reimbursement';

    public function label(): string
    {
        return __('transport.transport_types.'.$this->value);
    }
}
