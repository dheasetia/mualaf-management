<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Models\Convert;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\FollowUp\Models\ConvertLongTermFollowUp;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * PRD §31: for active converts without a long-term contact in the last
 * FOLLOW_UP_QUIET_DAYS days and no open schedule, suggests a follow-up to
 * the primary da'i as a high-priority schedule. It never writes a
 * follow-up status or the convert's status.
 */
final class FlagQuietConverts extends Command
{
    protected $signature = 'followups:flag-quiet';

    protected $description = 'Suggest follow-ups for converts nobody has contacted for a while';

    public function handle(TenantContext $tenantContext): int
    {
        $days = (int) config('followup.quiet_days');
        $since = CarbonImmutable::now()->subDays($days);
        $created = 0;

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $created += (int) $tenantContext->run((int) $tenantId, function () use ($since, $days): int {
                $count = 0;

                Convert::query()
                    ->where('status', ConvertStatus::Active)
                    ->where('created_at', '<', $since)
                    ->whereNotIn('id', ConvertLongTermFollowUp::query()->where('follow_up_at', '>=', $since)->select('convert_id'))
                    ->whereNotIn('id', ConvertFollowUpSchedule::query()->where('status', ScheduleStatus::Scheduled)->select('convert_id'))
                    ->each(function (Convert $convert) use (&$count, $days): void {
                        $schedule = new ConvertFollowUpSchedule;
                        $schedule->convert_id = $convert->id;
                        $schedule->dai_id = $convert->primary_dai_id;
                        $schedule->scheduled_at = CarbonImmutable::now();
                        $schedule->follow_up_type = 'check_in';
                        $schedule->priority = Priority::High;
                        $schedule->reason = (string) __('followup.quiet_reason', ['days' => $days]);
                        $schedule->status = ScheduleStatus::Scheduled;
                        $schedule->save();
                        $count++;
                    });

                return $count;
            });
        }

        $this->info("{$created} follow-up(s) suggested.");

        return self::SUCCESS;
    }
}
