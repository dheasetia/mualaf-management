<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum LetterType: string
{
    case ShahadahCertificate = 'shahadah_certificate';
    case NewConvertDataLetter = 'new_convert_data_letter';
    case KhitanReferralLetter = 'khitan_referral_letter';
    case ReligionUpdateLetter = 'religion_update_letter';
    case GuidanceCertificate = 'guidance_certificate';
    case OtherLetter = 'other_letter';

    public function label(): string
    {
        return __('documents.letter_types.'.$this->value);
    }
}
