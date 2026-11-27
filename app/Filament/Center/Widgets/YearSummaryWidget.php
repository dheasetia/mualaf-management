<?php

declare(strict_types=1);

namespace App\Filament\Center\Widgets;

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * This year's work across the modules, from the daily aggregates: the total
 * since 1 January, the last 30 days and a six-month trend line per figure.
 */
final class YearSummaryWidget extends StatsOverviewWidget
{
    /** @var list<Metric> */
    private const array METRICS = [
        Metric::ShahadahVerified,
        Metric::ProspectsCreated,
        Metric::FollowUpsRecorded,
        Metric::EnrollmentsCompleted,
        Metric::CertificatesIssued,
        Metric::PlansCompleted,
        Metric::SubmissionsCompleted,
    ];

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('reports.view_office') ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('dashboard.year_summary.heading', ['year' => CarbonImmutable::now((string) config('app.display_timezone'))->year]);
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $reader = app(ReportReader::class);
        $tenantId = app(TenantContext::class)->requireId();
        $today = CarbonImmutable::now((string) config('app.display_timezone'));

        return array_map(fn (Metric $metric): Stat => Stat::make(
            $metric->label(),
            number_format($reader->sum($metric, $tenantId, $today->startOfYear(), $today)),
        )
            ->description(__('dashboard.year_summary.last_30_days', ['count' => number_format($reader->sum($metric, $tenantId, $today->subDays(29), $today))]))
            ->chart(array_values($reader->monthly($metric, $tenantId, 6)))
            ->color($metric === Metric::ShahadahVerified ? 'success' : 'primary'), self::METRICS);
    }
}
