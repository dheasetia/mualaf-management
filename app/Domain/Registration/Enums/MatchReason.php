<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum MatchReason: string
{
    case ExactIdentity = 'exact_identity';
    case FuzzyNameDob = 'fuzzy_name_dob';
    case PhoneMatch = 'phone_match';
    case ManualReport = 'manual_report';

    public function label(): string
    {
        return __('duplicates.match_reasons.'.$this->value);
    }
}
