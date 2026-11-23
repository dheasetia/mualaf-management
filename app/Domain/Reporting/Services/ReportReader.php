<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Enums\Metric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dashboards read only the aggregate tables (15-operations: no live
 * aggregation). Flows are summed over a period; snapshots take the latest day.
 */
final class ReportReader
{
    public function sum(Metric $metric, ?int $tenantId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $this->query($tenantId)->where('metric', $metric->value)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])->sum('value');
    }

    public function latest(Metric|string $metric, ?int $tenantId): int
    {
        $metric = $metric instanceof Metric ? $metric->value : $metric;
        $query = $this->query($tenantId)->where('metric', $metric);
        $date = (clone $query)->max('stat_date');

        return $date === null ? 0 : (int) $query->where('stat_date', $date)->sum('value');
    }

    public function lastAggregatedOn(): ?string
    {
        $date = DB::table('report_daily_national_stats')->max('stat_date');

        return $date === null ? null : (string) $date;
    }

    /**
     * Monthly totals of a flow metric, oldest first: 'YYYY-MM' => value.
     *
     * @return array<string, int>
     */
    public function monthly(Metric $metric, ?int $tenantId, int $months): array
    {
        $from = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $rows = $this->query($tenantId)->where('metric', $metric->value)->where('stat_date', '>=', $from->toDateString())
            ->selectRaw("DATE_FORMAT(stat_date, '%Y-%m') as month, SUM(value) as total")->groupBy('month')->pluck('total', 'month');
        $result = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $from->addMonths($i)->format('Y-m');
            $result[$month] = (int) ($rows[$month] ?? 0);
        }

        return $result;
    }

    /**
     * Per-office totals of a flow metric: tenant_id => value.
     *
     * @return array<int, int>
     */
    public function byOffice(Metric $metric, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('report_daily_tenant_stats')->where('metric', $metric->value)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('tenant_id')->orderByDesc(DB::raw('SUM(value)'))
            ->pluck(DB::raw('SUM(value)'), 'tenant_id')->map(fn (mixed $value): int => (int) $value)->all();
    }

    /**
     * Conversions by nationality nationally: country_id => value.
     *
     * @return array<int, int>
     */
    public function shahadahByNationality(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        return DB::table('report_daily_national_stats')->where('metric', Metric::ShahadahVerified->value)->where('dimension', 'nationality')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('dimension_value')->orderByDesc(DB::raw('SUM(value)'))->limit($limit)
            ->pluck(DB::raw('SUM(value)'), 'dimension_value')->mapWithKeys(fn (mixed $value, mixed $key): array => [(int) $key => (int) $value])->all();
    }

    private function query(?int $tenantId): Builder
    {
        return $tenantId === null
            ? DB::table('report_daily_national_stats')->where('dimension', 'all')
            : DB::table('report_daily_tenant_stats')->where('tenant_id', $tenantId);
    }
}
