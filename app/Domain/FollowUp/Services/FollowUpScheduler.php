<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Services;

use App\Domain\Converts\Models\Convert;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Domain\FollowUp\Enums\TriggerEvent;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\FollowUp\Models\FollowUpScheduleTemplate;
use Carbon\CarbonImmutable;

/**
 * Creates the follow-up schedule of an event from the office's active
 * template for that trigger, else the national one (10 §10.4), for the
 * convert's primary da'i. Runs inside the office's tenant context; an
 * event already scheduled from the same template is not scheduled twice.
 */
final class FollowUpScheduler
{
    /** @return int number of schedules created */
    public function schedule(TriggerEvent $trigger, int $convertId, CarbonImmutable $from): int
    {
        $template = FollowUpScheduleTemplate::query()
            ->where('trigger_event', $trigger)
            ->where('active', true)
            ->orderByRaw('tenant_id IS NULL')
            ->first();
        $convert = Convert::query()->find($convertId);

        if ($template === null || $convert === null || $convert->status->isTerminal()) {
            return 0;
        }

        $created = 0;

        foreach ($template->offsets as $days) {
            $at = $from->addDays((int) $days)->setTime(9, 0);
            $exists = ConvertFollowUpSchedule::query()->where('convert_id', $convertId)->where('template_id', $template->id)
                ->where('scheduled_at', $at)->exists();

            if ($exists) {
                continue;
            }

            $schedule = new ConvertFollowUpSchedule;
            $schedule->convert_id = $convertId;
            $schedule->dai_id = $convert->primary_dai_id;
            $schedule->template_id = $template->id;
            $schedule->scheduled_at = $at;
            $schedule->follow_up_type = $trigger->value;
            $schedule->priority = Priority::Normal;
            $schedule->status = ScheduleStatus::Scheduled;
            $schedule->save();
            $created++;
        }

        return $created;
    }
}
