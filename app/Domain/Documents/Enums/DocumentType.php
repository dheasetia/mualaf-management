<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum DocumentType: string
{
    case IqamahCopy = 'iqamah_copy';
    case PassportCopy = 'passport_copy';
    case Photo = 'photo';
    case MedicalLetter = 'medical_letter';
    case Receipt = 'receipt';
    case Other = 'other';

    public function label(): string
    {
        return __('documents.document_types.'.$this->value);
    }
}
