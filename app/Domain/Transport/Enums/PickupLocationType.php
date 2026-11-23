<?php

declare(strict_types=1);

namespace App\Domain\Transport\Enums;

enum PickupLocationType: string
{
    case Residence = 'residence';
    case Workplace = 'workplace';
    case Custom = 'custom';

    public function label(): string
    {
        return __('transport.location_types.'.$this->value);
    }
}
