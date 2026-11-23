<?php

declare(strict_types=1);

namespace App\Filament\Central\Pages;

use App\Domain\Platform\Models\Country;
use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * National dashboard (reports.view_national), read from the aggregate
 * tables: totals, offices and nationalities.
 */
final class NationalDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $slug = 'national-dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.reports.national-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('reports.view_national') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('reports.national_dashboard');
    }

    public function getTitle(): string
    {
        return __('reports.national_dashboard');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $reader = app(ReportReader::class);
        $today = CarbonImmutable::now((string) config('app.display_timezone'));
        $year = $today->startOfYear();
        $offices = collect(LocalizedName::map(Tenant::query()));
        $countries = collect(LocalizedName::map(Country::query()));

        return [
            'lastAggregated' => $reader->lastAggregatedOn(),
            'snapshots' => [
                Metric::ConvertsActive->label() => $reader->latest(Metric::ConvertsActive, null),
                __('reports.metrics.converts_total') => $reader->latest('converts_total', null),
                Metric::FollowUpsOverdue->label() => $reader->latest(Metric::FollowUpsOverdue, null),
            ],
            'flows' => collect([Metric::ShahadahVerified, Metric::ProspectsCreated, Metric::EnrollmentsCompleted, Metric::CertificatesIssued])
                ->mapWithKeys(fn (Metric $metric): array => [$metric->label() => [
                    $reader->sum($metric, null, $today->subDays(29), $today),
                    $reader->sum($metric, null, $year, $today),
                ]])->all(),
            'monthly' => $reader->monthly(Metric::ShahadahVerified, null, 12),
            'byOffice' => collect($reader->byOffice(Metric::ShahadahVerified, $year, $today))
                ->mapWithKeys(fn (int $value, int $tenantId): array => [(string) ($offices[$tenantId] ?? $tenantId) => $value])->all(),
            'byNationality' => collect($reader->shahadahByNationality($year, $today))
                ->mapWithKeys(fn (int $value, int $countryId): array => [(string) ($countries[$countryId] ?? $countryId) => $value])->all(),
        ];
    }
}
