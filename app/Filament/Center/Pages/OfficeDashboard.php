<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Support\Tenancy\TenantContext;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Office dashboard (reports.view_office), read from the aggregate tables.
 */
final class OfficeDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $slug = 'dashboard-report';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.reports.office-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('reports.view_office') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('reports.office_dashboard');
    }

    public static function getNavigationGroup(): string
    {
        return __('reports.navigation_group');
    }

    public function getTitle(): string
    {
        return __('reports.office_dashboard');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $reader = app(ReportReader::class);
        $tenantId = app(TenantContext::class)->requireId();
        $today = CarbonImmutable::now((string) config('app.display_timezone'));
        $flows = [Metric::ShahadahVerified, Metric::ProspectsCreated, Metric::FollowUpsRecorded, Metric::EnrollmentsCompleted, Metric::CertificatesIssued, Metric::PlansCompleted, Metric::SubmissionsCompleted];

        return [
            'lastAggregated' => $reader->lastAggregatedOn(),
            'snapshots' => collect([Metric::ConvertsActive, Metric::ProspectsOpen, Metric::FollowUpsOverdue])
                ->mapWithKeys(fn (Metric $metric): array => [$metric->label() => $reader->latest($metric, $tenantId)])->all(),
            'flows' => collect($flows)->mapWithKeys(fn (Metric $metric): array => [$metric->label() => [
                $reader->sum($metric, $tenantId, $today->subDays(29), $today),
                $reader->sum($metric, $tenantId, $today->startOfYear(), $today),
            ]])->all(),
            'monthly' => $reader->monthly(Metric::ShahadahVerified, $tenantId, 12),
        ];
    }
}
