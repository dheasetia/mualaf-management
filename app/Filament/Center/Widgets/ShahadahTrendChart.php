<?php

declare(strict_types=1);

namespace App\Filament\Center\Widgets;

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Support\Tenancy\TenantContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Verified shahadah per month over the last twelve months (daily aggregates).
 */
final class ShahadahTrendChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('reports.view_office') ?? false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.charts.shahadah_heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('dashboard.charts.shahadah_description');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $monthly = app(ReportReader::class)->monthly(Metric::ShahadahVerified, app(TenantContext::class)->requireId(), 12);

        return [
            'datasets' => [[
                'label' => Metric::ShahadahVerified->label(),
                'data' => array_values($monthly),
                'borderColor' => '#075B43',
                'backgroundColor' => 'rgba(7, 91, 67, 0.12)',
                'pointBackgroundColor' => '#B79755',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_keys($monthly),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
