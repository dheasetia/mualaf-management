<?php

declare(strict_types=1);

namespace App\Filament\Central\Widgets;

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Localization\LocalizedName;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Verified shahadah this year per center (daily aggregates).
 */
final class OfficeShahadahChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('reports.view_national') ?? false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.national.chart_heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('dashboard.national.chart_description');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $today = CarbonImmutable::now((string) config('app.display_timezone'));
        $byOffice = app(ReportReader::class)->byOffice(Metric::ShahadahVerified, $today->startOfYear(), $today);
        $names = LocalizedName::map(Tenant::query());

        return [
            'datasets' => [[
                'label' => Metric::ShahadahVerified->label(),
                'data' => array_values($byOffice),
                'backgroundColor' => '#075B43',
                'borderRadius' => 4,
            ]],
            'labels' => array_map(fn (int $tenantId): string => (string) ($names[$tenantId] ?? $tenantId), array_keys($byOffice)),
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
