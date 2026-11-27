<?php

declare(strict_types=1);

namespace App\Filament\Central\Widgets;

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\ReportReader;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Localization\LocalizedName;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/**
 * Active centers side by side, from the daily aggregates: shahadah this year,
 * active new Muslims, overdue follow-ups and the last day with any recorded
 * activity (quiet centers are marked).
 */
final class OfficeOverviewWidget extends TableWidget
{
    public const int QUIET_AFTER_DAYS = 14;

    /** Daily flows that count as activity. */
    private const array ACTIVITY_METRICS = [
        Metric::ShahadahVerified, Metric::ProspectsCreated, Metric::FollowUpsRecorded,
        Metric::EnrollmentsCompleted, Metric::CertificatesIssued, Metric::PlansCompleted, Metric::SubmissionsCompleted,
    ];

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /** @var array{shahadah: array<int, int>, converts: array<int, int>, overdue: array<int, int>, activity: array<int, string>}|null */
    private ?array $figures = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('reports.view_national') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.national.offices_heading'))
            ->description(__('dashboard.national.offices_description', ['days' => self::QUIET_AFTER_DAYS]))
            ->query(fn () => Tenant::query()->where('status', TenantStatus::Active))
            ->defaultSort(LocalizedName::column())
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make(LocalizedName::column())->label(__('dashboard.national.office'))->searchable(['name_ar', 'name_en', 'code']),
                TextColumn::make('shahadah_year')->label(__('dashboard.national.shahadah_year'))
                    ->state(fn (Tenant $record): int => $this->figures()['shahadah'][$record->id] ?? 0),
                TextColumn::make('converts_active')->label(__('dashboard.stats.converts_active'))
                    ->state(fn (Tenant $record): int => $this->figures()['converts'][$record->id] ?? 0),
                TextColumn::make('follow_ups_overdue')->label(__('dashboard.stats.follow_ups_overdue'))
                    ->state(fn (Tenant $record): int => $this->figures()['overdue'][$record->id] ?? 0)
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('last_activity')->label(__('dashboard.national.last_activity'))->badge()
                    ->state(fn (Tenant $record): string => $this->figures()['activity'][$record->id] ?? __('dashboard.national.no_activity'))
                    ->color(fn (Tenant $record): string => self::isQuiet($this->figures()['activity'][$record->id] ?? null) ? 'danger' : 'success'),
            ]);
    }

    private static function isQuiet(?string $lastActivity): bool
    {
        return $lastActivity === null
            || CarbonImmutable::parse($lastActivity)->lessThan(CarbonImmutable::now((string) config('app.display_timezone'))->subDays(self::QUIET_AFTER_DAYS)->startOfDay());
    }

    /** @return array{shahadah: array<int, int>, converts: array<int, int>, overdue: array<int, int>, activity: array<int, string>} */
    private function figures(): array
    {
        if ($this->figures !== null) {
            return $this->figures;
        }

        $reader = app(ReportReader::class);
        $today = CarbonImmutable::now((string) config('app.display_timezone'));

        return $this->figures = [
            'shahadah' => $reader->byOffice(Metric::ShahadahVerified, $today->startOfYear(), $today),
            'converts' => $this->latestByOffice(Metric::ConvertsActive),
            'overdue' => $this->latestByOffice(Metric::FollowUpsOverdue),
            'activity' => DB::table('report_daily_tenant_stats')
                ->where('value', '>', 0)
                ->whereIn('metric', array_map(fn (Metric $metric): string => $metric->value, self::ACTIVITY_METRICS))
                ->groupBy('tenant_id')
                ->pluck(DB::raw('MAX(stat_date)'), 'tenant_id')
                ->map(fn (mixed $date): string => substr((string) $date, 0, 10))
                ->all(),
        ];
    }

    /**
     * Snapshot metric per office on its latest aggregated day.
     *
     * @return array<int, int>
     */
    private function latestByOffice(Metric $metric): array
    {
        $latest = DB::table('report_daily_tenant_stats')->where('metric', $metric->value)
            ->select('tenant_id', DB::raw('MAX(stat_date) as stat_date'))->groupBy('tenant_id');

        return DB::table('report_daily_tenant_stats as stats')
            ->joinSub($latest, 'latest', fn ($join) => $join->on('latest.tenant_id', '=', 'stats.tenant_id')->on('latest.stat_date', '=', 'stats.stat_date'))
            ->where('stats.metric', $metric->value)
            ->pluck('stats.value', 'stats.tenant_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
    }
}
