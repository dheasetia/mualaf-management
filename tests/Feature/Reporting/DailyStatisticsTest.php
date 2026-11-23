<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\Platform\Models\Country;
use App\Domain\Reporting\Enums\Metric;
use App\Domain\Reporting\Services\DailyStatistics;
use App\Domain\Reporting\Services\ReportReader;
use App\Domain\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\FollowUpScheduleTemplateSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    seedRbac();
    $this->seed(FollowUpScheduleTemplateSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->dammam = Tenant::factory()->active()->create();
    $this->riyadh = Tenant::factory()->active()->create();
    $this->day = CarbonImmutable::parse('2026-11-01', (string) config('app.display_timezone'));
    $this->tenantStat = fn (Tenant $tenant, Metric $metric): ?int => ($value = DB::table('report_daily_tenant_stats')
        ->where(['tenant_id' => $tenant->id, 'stat_date' => $this->day->toDateString(), 'metric' => $metric->value])->value('value')) === null ? null : (int) $value;
});

it('counts first verified shahadahs by the office where they happened, even after a transfer', function (): void {
    $first = documentsConvert($this->dammam, identity: '277005511');
    documentsConvert($this->dammam, identity: '277005522');
    documentsConvert($this->riyadh, identity: '277005533');
    documentsConvert($this->riyadh, verified: false, identity: '277005544');

    Convert::query()->withoutGlobalScopes()->whereKey($first->id)->update(['current_tenant_id' => $this->riyadh->id]);
    app(DailyStatistics::class)->aggregate($this->day);

    expect(($this->tenantStat)($this->dammam, Metric::ShahadahVerified))->toBe(2)
        ->and(($this->tenantStat)($this->riyadh, Metric::ShahadahVerified))->toBe(1)
        ->and((int) DB::table('report_daily_national_stats')->where(['stat_date' => '2026-11-01', 'metric' => 'shahadah_verified', 'dimension' => 'all'])->value('value'))->toBe(3)
        ->and((int) DB::table('report_daily_national_stats')->where(['stat_date' => '2026-11-01', 'metric' => 'shahadah_verified', 'dimension' => 'nationality'])->sum('value'))->toBe(3)
        ->and((int) DB::table('report_daily_national_stats')->where(['stat_date' => '2026-11-01', 'metric' => 'converts_total'])->value('value'))->toBe(4);
});

it('counts active converts in their current office and overdue follow-ups as snapshots', function (): void {
    $convert = documentsConvert($this->dammam);
    $this->day = CarbonImmutable::now((string) config('app.display_timezone'));
    inTenant($this->dammam, fn () => ConvertFollowUpSchedule::query()->update(['scheduled_at' => now()->subHour()]));

    app(DailyStatistics::class)->aggregate($this->day);

    expect(($this->tenantStat)($this->dammam, Metric::ConvertsActive))->toBe(1)
        ->and(($this->tenantStat)($this->dammam, Metric::FollowUpsOverdue))->toBe(5)
        ->and(inTenant($this->dammam, fn () => ConvertFollowUpSchedule::query()->where('convert_id', $convert->id)->count()))->toBe(5)
        ->and(($this->tenantStat)($this->riyadh, Metric::ConvertsActive))->toBeNull();
});

it('replaces a day on re-aggregation and feeds the dashboard reader', function (): void {
    documentsConvert($this->dammam);

    app(DailyStatistics::class)->aggregate($this->day);
    app(DailyStatistics::class)->aggregate($this->day);
    $this->artisan('reports:aggregate', ['--date' => '2026-11-01'])->assertSuccessful();

    $reader = app(ReportReader::class);
    expect(DB::table('report_daily_tenant_stats')->where(['stat_date' => '2026-11-01', 'metric' => 'shahadah_verified'])->count())->toBe(1)
        ->and($reader->sum(Metric::ShahadahVerified, $this->dammam->id, $this->day->subDays(5), $this->day))->toBe(1)
        ->and($reader->monthly(Metric::ShahadahVerified, null, 12)['2026-11'] ?? null)->toBe(1)
        ->and($reader->byOffice(Metric::ShahadahVerified, $this->day->startOfYear(), $this->day))->toBe([$this->dammam->id => 1]);
});
