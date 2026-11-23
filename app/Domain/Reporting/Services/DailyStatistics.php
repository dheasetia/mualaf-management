<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Enums\Metric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Nightly aggregation into report_daily_* (11 §11.8). Reads tables directly
 * across offices (it is the reporting layer, run by the scheduler) and
 * replaces the day's rows, so a re-run is safe. Days follow the display
 * timezone. Conversions count first, verified shahadah records by the
 * office where they happened, so transfers never move or double them.
 */
final class DailyStatistics
{
    /** @return int rows written */
    public function aggregate(CarbonImmutable $day): int
    {
        $timezone = (string) config('app.display_timezone');
        $date = $day->setTimezone($timezone)->toDateString();
        $start = CarbonImmutable::parse($date, $timezone)->startOfDay()->utc();
        $end = $start->addDay();
        $between = fn (Builder $query, string $column): Builder => $query->where($column, '>=', $start)->where($column, '<', $end);

        $perTenant = [
            Metric::ShahadahVerified->value => $this->firstShahadahs()->where('shahadah_date', $date)->groupBy('s.tenant_id')->pluck(DB::raw('COUNT(*)'), 's.tenant_id'),
            Metric::ProspectsCreated->value => $between(DB::table('prospects'), 'created_at')->groupBy('tenant_id')->pluck(DB::raw('COUNT(*)'), 'tenant_id'),
            Metric::FollowUpsRecorded->value => $this->sumMaps(
                $between(DB::table('dai_follow_ups'), 'created_at')->groupBy('tenant_id')->pluck(DB::raw('COUNT(*)'), 'tenant_id'),
                $between(DB::table('convert_long_term_follow_ups'), 'follow_up_at')->groupBy('tenant_id')->pluck(DB::raw('COUNT(*)'), 'tenant_id'),
            ),
            Metric::EnrollmentsCompleted->value => $between(DB::table('enrollments'), 'completed_at')->groupBy('service_tenant_id')->pluck(DB::raw('COUNT(*)'), 'service_tenant_id'),
            Metric::CertificatesIssued->value => $between(DB::table('certificates'), 'issued_at')->groupBy('tenant_id')->pluck(DB::raw('COUNT(*)'), 'tenant_id'),
            Metric::PlansCompleted->value => DB::table('dawah_activity_plans')->where('planned_date', $date)->whereIn('status', ['completed', 'report_submitted', 'verified'])
                ->groupBy('tenant_id')->pluck(DB::raw('COUNT(*)'), 'tenant_id'),
            Metric::SubmissionsCompleted->value => $between(DB::table('submissions'), 'completed_at')->groupBy('tenant_id')->pluck(DB::raw('COUNT(*)'), 'tenant_id'),
            Metric::ConvertsActive->value => DB::table('converts')->where('status', 'active')->where('created_at', '<', $end)->groupBy('current_tenant_id')->pluck(DB::raw('COUNT(*)'), 'current_tenant_id'),
            Metric::ProspectsOpen->value => DB::table('prospects')->whereNotIn('status', ['converted', 'transferred', 'closed'])->where('created_at', '<', $end)
                ->groupBy('tenant_id')->pluck(DB::raw('COUNT(*)'), 'tenant_id'),
            Metric::FollowUpsOverdue->value => DB::table('convert_follow_up_schedules')->where('status', 'scheduled')->where('scheduled_at', '<', $end)
                ->groupBy('tenant_id')->pluck(DB::raw('COUNT(*)'), 'tenant_id'),
        ];

        return DB::transaction(function () use ($date, $perTenant): int {
            DB::table('report_daily_tenant_stats')->where('stat_date', $date)->delete();
            DB::table('report_daily_national_stats')->where('stat_date', $date)->delete();
            $tenantRows = [];
            $nationalRows = [];

            foreach ($perTenant as $metric => $values) {
                foreach ($values as $tenantId => $value) {
                    $tenantRows[] = ['tenant_id' => (int) $tenantId, 'stat_date' => $date, 'metric' => $metric, 'value' => (int) $value];
                }

                $nationalRows[] = ['stat_date' => $date, 'metric' => $metric, 'dimension' => 'all', 'dimension_value' => 'all', 'value' => (int) $values->sum()];
            }

            foreach ($this->firstShahadahs()->where('shahadah_date', $date)->groupBy('c.nationality_id')->pluck(DB::raw('COUNT(*)'), 'c.nationality_id') as $countryId => $value) {
                $nationalRows[] = ['stat_date' => $date, 'metric' => Metric::ShahadahVerified->value, 'dimension' => 'nationality', 'dimension_value' => (string) $countryId, 'value' => (int) $value];
            }

            $nationalRows[] = ['stat_date' => $date, 'metric' => 'converts_total', 'dimension' => 'all', 'dimension_value' => 'all', 'value' => DB::table('converts')->count()];

            foreach (array_chunk($tenantRows, 500) as $chunk) {
                DB::table('report_daily_tenant_stats')->insert($chunk);
            }

            DB::table('report_daily_national_stats')->insert($nationalRows);

            return count($tenantRows) + count($nationalRows);
        });
    }

    /** First, verified shahadah records with their convert. */
    private function firstShahadahs(): Builder
    {
        return DB::table('shahadah_records as s')->join('converts as c', 'c.id', '=', 's.convert_id')
            ->where('s.record_type', 'first')->where('s.status', 'verified');
    }

    /**
     * @param  Collection<array-key, mixed>  $first
     * @param  Collection<array-key, mixed>  $second
     * @return Collection<array-key, int>
     */
    private function sumMaps(Collection $first, Collection $second): Collection
    {
        return $first->keys()->merge($second->keys())->unique()->mapWithKeys(fn (int|string $key): array => [
            $key => (int) $first->get($key, 0) + (int) $second->get($key, 0),
        ]);
    }
}
