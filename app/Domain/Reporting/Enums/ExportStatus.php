<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

enum ExportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return __('reports.export_statuses.'.$this->value);
    }
}
