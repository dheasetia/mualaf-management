<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

enum ResidenceType: string
{
    case CompanyDormitory = 'company_dormitory';
    case WorkersCamp = 'workers_camp';
    case House = 'house';
    case Apartment = 'apartment';
    case Other = 'other';

    public function label(): string
    {
        return __('converts.residence_types.'.$this->value);
    }
}
