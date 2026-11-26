<?php

declare(strict_types=1);

namespace App\Filament\Center\Widgets;

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Support\Tenancy\TenantContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Monthly office activity over six months: prospects, follow-ups, completed
 * guidance and certificates (daily aggregates).
 */
final class ActivityTrendChart extends ChartWidget
{
    /** @var array<string, string> metric value => colour */
    private const array SERIES = [
        Metric::ProspectsCreated->value => '#075B43',
        Metric::FollowUpsRecorded->value => '#B79755',
        Metric::EnrollmentsCompleted->value => '#479A7C',
        Metric::CertificatesIssued->value => '#103E32',
    ];

    protected static ?int $sort = 6;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('reports.view_office') ?? false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.charts.activity_heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('dashboard.charts.activity_description');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $reader = app(ReportReader::class);
        $tenantId = app(TenantContext::class)->requireId();
        $labels = [];
        $datasets = [];

        foreach (self::SERIES as $value => $colour) {
            $metric = Metric::from($value);
            $monthly = $reader->monthly($metric, $tenantId, 6);
            $labels = array_keys($monthly);
            $datasets[] = ['label' => $metric->label(), 'data' => array_values($monthly), 'backgroundColor' => $colour, 'borderRadius' => 4];
        }

        return ['datasets' => $datasets, 'labels' => $labels];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
