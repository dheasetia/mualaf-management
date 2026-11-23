<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Exports;

use App\Domain\Reporting\Enums\ExportType;
use App\Filament\Center\Resources\Exports\Pages\ListExports;
use App\Filament\Reporting\ExportResource as BaseExportResource;

final class ExportResource extends BaseExportResource
{
    public static function getNavigationGroup(): string
    {
        return __('reports.navigation_group');
    }

    /** @return list<ExportType> */
    protected static function types(): array
    {
        return [ExportType::Converts, ExportType::ShahadahStatistics];
    }

    public static function getPages(): array
    {
        return ['index' => ListExports::route('/')];
    }
}
