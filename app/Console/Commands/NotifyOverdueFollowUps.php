<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\Notifications\Contracts\OfficeLinks;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Notifications\Services\OfficeRecipients;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * 12 §12.6 FollowUpOverdue: each morning every da'i with overdue follow-ups
 * gets one summary; follow-ups overdue for more than 7 days are escalated
 * once to holders of followups.view_confidential (Head of Dawah).
 */
final class NotifyOverdueFollowUps extends Command
{
    protected $signature = 'followups:notify-overdue';

    protected $description = 'Remind da\'i of overdue follow-ups and escalate long-overdue ones';

    public function handle(TenantContext $tenantContext, OfficeRecipients $recipients, OfficeLinks $links): int
    {
        $sent = 0;

        foreach (Tenant::query()->get() as $tenant) {
            $sent += (int) $tenantContext->run($tenant, function () use ($tenant, $recipients, $links): int {
                $count = 0;
                $url = $links->followUpSchedule($tenant);
                $overdue = ConvertFollowUpSchedule::query()->overdue()->get(['id', 'dai_id', 'scheduled_at', 'escalated_at']);

                foreach ($overdue->groupBy('dai_id') as $daiId => $schedules) {
                    $user = $recipients->daiUser($tenant->id, (int) $daiId);

                    if ($user !== null) {
                        $user->notify(new OfficeAlert('follow_ups_overdue', $tenant->id, ['count' => $schedules->count()], $url));
                        $count++;
                    }
                }

                $longOverdue = $overdue->filter(fn (ConvertFollowUpSchedule $schedule): bool => $schedule->getAttribute('escalated_at') === null
                    && $schedule->scheduled_at->lessThan(CarbonImmutable::now()->subDays(7)));

                if ($longOverdue->isNotEmpty()) {
                    $heads = $recipients->withPermission($tenant->id, 'followups.view_confidential');
                    Notification::send($heads, new OfficeAlert('follow_ups_escalated', $tenant->id, ['count' => $longOverdue->count()], $url));
                    ConvertFollowUpSchedule::query()->whereKey($longOverdue->modelKeys())->update(['escalated_at' => CarbonImmutable::now()]);
                    $count += $heads->count();
                }

                return $count;
            });
        }

        $this->info("{$sent} notification(s) sent.");

        return self::SUCCESS;
    }
}
