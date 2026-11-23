<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reporting\Services\DailyStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * 11 §11.8: nightly statistics. Re-aggregates the last few days so late
 * verifications and corrections land in the right day.
 */
final class AggregateReports extends Command
{
    protected $signature = 'reports:aggregate {--date= : A single day (Y-m-d), else the last --days days} {--days=7}';

    protected $description = 'Aggregate daily office and national statistics';

    public function handle(DailyStatistics $statistics): int
    {
        $timezone = (string) config('app.display_timezone');
        $days = $this->option('date') !== null
            ? [CarbonImmutable::parse((string) $this->option('date'), $timezone)]
            : array_map(fn (int $back): CarbonImmutable => CarbonImmutable::now($timezone)->subDays($back), range(0, max(0, (int) $this->option('days') - 1)));

        foreach ($days as $day) {
            $rows = $statistics->aggregate($day);
            $this->line($day->toDateString().": {$rows} row(s)");
        }

        return self::SUCCESS;
    }
}
