<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum CandidateType: string
{
    case Prospect = 'prospect';
    case RegistrationDraft = 'registration_draft';
    case ImportRow = 'import_row';

    public function label(): string
    {
        return __('duplicates.candidate_types.'.$this->value);
    }
}
