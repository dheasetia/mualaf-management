<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum ScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
    case Skipped = 'skipped';

    /** Usable: scanned clean, or scanning is switched off. */
    public function isSafe(): bool
    {
        return $this === self::Clean || $this === self::Skipped;
    }

    public function label(): string
    {
        return __('documents.scan_statuses.'.$this->value);
    }
}
