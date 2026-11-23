<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Enums;

enum CertificateType: string
{
    case Shahadah = 'shahadah';
    case GuidanceCompletion = 'guidance_completion';

    /** Short code in certificate numbers: {TENANT}-SH-2026-000001. */
    public function numberCode(): string
    {
        return match ($this) {
            self::Shahadah => 'SH',
            self::GuidanceCompletion => 'GC',
        };
    }

    public function label(): string
    {
        return __('certificates.types.'.$this->value);
    }
}
