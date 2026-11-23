<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

enum ExportType: string
{
    case Converts = 'converts';
    case ShahadahStatistics = 'shahadah_statistics';

    /** Personal data: needs a reason and follows disclosure levels (11 §11.5). */
    public function isSensitive(): bool
    {
        return $this === self::Converts;
    }

    public function label(): string
    {
        return __('reports.export_types.'.$this->value);
    }
}
