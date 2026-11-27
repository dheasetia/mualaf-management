<?php

declare(strict_types=1);

namespace App\Filament\Central\Widgets;

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * National figures from the daily aggregates; no personal data.
 */
final class NationalStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('reports.view_national') ?? false;
    }

    protected function getDescription(): ?string
    {
        $lastAggregated = app(ReportReader::class)->lastAggregatedOn();

        return $lastAggregated === null ? __('dashboard.stats.not_aggregated') : __('dashboard.stats.as_of', ['date' => $lastAggregated]);
    }

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $reader = app(ReportReader::class);
        $today = CarbonImmutable::now((string) config('app.display_timezone'));
        $overdue = $reader->latest(Metric::FollowUpsOverdue, null);

        return [
            Stat::make(__('dashboard.stats.converts_active'), number_format($reader->latest(Metric::ConvertsActive, null)))
                ->descriptionIcon(Heroicon::OutlinedUsers),
            Stat::make(__('dashboard.stats.shahadah_this_month'), number_format($reader->sum(Metric::ShahadahVerified, null, $today->startOfMonth(), $today)))
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->chart(array_values($reader->monthly(Metric::ShahadahVerified, null, 6)))
                ->color('success'),
            Stat::make(__('dashboard.stats.prospects_open'), number_format($reader->latest(Metric::ProspectsOpen, null)))
                ->descriptionIcon(Heroicon::OutlinedUserGroup),
            Stat::make(__('dashboard.stats.follow_ups_overdue'), number_format($overdue))
                ->descriptionIcon(Heroicon::OutlinedExclamationCircle)
                ->color($overdue > 0 ? 'danger' : 'gray'),
            Stat::make(__('dashboard.national.offices_active'), number_format(Tenant::query()->where('status', TenantStatus::Active)->count()))
                ->description(__('dashboard.national.offices_active_help'))
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2),
        ];
    }
}
