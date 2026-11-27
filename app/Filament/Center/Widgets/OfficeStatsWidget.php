<?php

declare(strict_types=1);

namespace App\Filament\Center\Widgets;

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Office figures from the daily aggregates (reports:aggregate), the same
 * source as the office report page; no personal data.
 */
final class OfficeStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('reports.view_office') ?? false;
    }

    protected function getDescription(): ?string
    {
        $lastAggregated = app(ReportReader::class)->lastAggregatedOn();

        return $lastAggregated === null ? __('dashboard.stats.not_aggregated') : __('dashboard.stats.as_of', ['date' => $lastAggregated]);
    }

    protected function getStats(): array
    {
        $reader = app(ReportReader::class);
        $tenantId = app(TenantContext::class)->requireId();
        $today = CarbonImmutable::now((string) config('app.display_timezone'));
        $overdue = $reader->latest(Metric::FollowUpsOverdue, $tenantId);

        return [
            Stat::make(__('dashboard.stats.converts_active'), number_format($reader->latest(Metric::ConvertsActive, $tenantId)))
                ->description(__('dashboard.stats.converts_active_help'))
                ->descriptionIcon(Heroicon::OutlinedUsers),
            Stat::make(__('dashboard.stats.shahadah_this_month'), number_format($reader->sum(Metric::ShahadahVerified, $tenantId, $today->startOfMonth(), $today)))
                ->description(__('dashboard.stats.shahadah_this_month_help'))
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->color('success'),
            Stat::make(__('dashboard.stats.prospects_open'), number_format($reader->latest(Metric::ProspectsOpen, $tenantId)))
                ->description(__('dashboard.stats.prospects_open_help'))
                ->descriptionIcon(Heroicon::OutlinedUserGroup),
            Stat::make(__('dashboard.stats.follow_ups_overdue'), number_format($overdue))
                ->description(__('dashboard.stats.follow_ups_overdue_help'))
                ->descriptionIcon(Heroicon::OutlinedExclamationCircle)
                ->color($overdue > 0 ? 'danger' : 'gray'),
        ];
    }
}
