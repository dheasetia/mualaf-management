<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\FollowUp\Enums\ContactedPerson;
use App\Domain\FollowUp\Enums\ContactMethod;
use App\Domain\FollowUp\Enums\ContactResult;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Domain\FollowUp\Exceptions\FollowUpRuleViolation;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\FollowUp\Models\ConvertLongTermFollowUp;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Long-term follow-up contacts and schedules (10 §10.4–10.5). Recording a
 * contact for a scheduled follow-up completes it; a next date schedules the
 * following one. Schedules can be missed, rescheduled or cancelled with a
 * reason by the convert's follow-up staff.
 */
final readonly class LongTermFollowUps
{
    public function __construct(private DaiDirectory $dais, private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function record(Convert $convert, array $data, ?ConvertFollowUpSchedule $schedule, User $actor): ConvertLongTermFollowUp
    {
        $this->authorize($convert, $actor);
        $daiId = $this->dais->daiIdForUser($actor) ?? $convert->primary_dai_id;

        if ($schedule !== null && $schedule->convert_id !== $convert->id) {
            throw FollowUpRuleViolation::scheduleOfOtherConvert();
        }

        return DB::transaction(function () use ($convert, $data, $schedule, $actor, $daiId): ConvertLongTermFollowUp {
            $note = new ConvertLongTermFollowUp;
            $note->convert_id = $convert->id;
            $note->dai_id = $daiId;
            $note->follow_up_at = blank($data['follow_up_at'] ?? null) ? CarbonImmutable::now() : CarbonImmutable::parse((string) $data['follow_up_at']);
            $note->contact_method = ContactMethod::from((string) $data['contact_method']);
            $note->contacted_person_type = ContactedPerson::from((string) $data['contacted_person_type']);
            $note->contact_result = ContactResult::from((string) $data['contact_result']);

            foreach (['current_city', 'mosque_or_center_name', 'local_dai_name', 'support_needed', 'next_action', 'notes'] as $field) {
                $note->setAttribute($field, blank($data[$field] ?? null) ? null : (string) $data[$field]);
            }

            $note->current_country_id = blank($data['current_country_id'] ?? null) ? null : (int) $data['current_country_id'];
            $note->next_follow_up_at = blank($data['next_follow_up_at'] ?? null) ? null : CarbonImmutable::parse((string) $data['next_follow_up_at']);
            $note->confidentiality_level = Confidentiality::tryFrom((string) ($data['confidentiality_level'] ?? '')) ?? Confidentiality::Normal;
            $note->recorded_by = $actor->id;
            $note->save();

            if ($schedule !== null) {
                $locked = ConvertFollowUpSchedule::query()->lockForUpdate()->findOrFail($schedule->id);

                if ($locked->status !== ScheduleStatus::Scheduled) {
                    throw FollowUpRuleViolation::invalidTransition();
                }

                $locked->status = ScheduleStatus::Completed;
                $locked->completed_at = CarbonImmutable::now();
                $locked->outcome = $note->contact_result->value;
                $locked->long_term_follow_up_id = $note->id;
                $locked->save();
            }

            if ($note->next_follow_up_at !== null) {
                $this->add($convert, $note->next_follow_up_at, 'long_term', Priority::Normal, null, $convert->primary_dai_id);
            }

            $this->audit->record('followup.long_term_recorded', $convert, newValues: ['follow_up_id' => $note->id, 'result' => $note->contact_result->value], actor: $actor);

            return $note;
        });
    }

    public function miss(ConvertFollowUpSchedule $schedule, User $actor): ConvertFollowUpSchedule
    {
        return $this->close($schedule, ScheduleStatus::Missed, null, $actor);
    }

    public function cancel(ConvertFollowUpSchedule $schedule, ?string $reason, User $actor): ConvertFollowUpSchedule
    {
        return $this->close($schedule, ScheduleStatus::Cancelled, blank($reason) ? throw FollowUpRuleViolation::reasonRequired() : trim((string) $reason), $actor);
    }

    public function reschedule(ConvertFollowUpSchedule $schedule, CarbonImmutable $at, User $actor): ConvertFollowUpSchedule
    {
        if ($at->isPast()) {
            throw FollowUpRuleViolation::dateInPast();
        }

        return DB::transaction(function () use ($schedule, $at, $actor): ConvertFollowUpSchedule {
            $this->close($schedule, ScheduleStatus::Rescheduled, null, $actor);
            $convert = Convert::query()->findOrFail($schedule->convert_id);

            return $this->add($convert, $at, $schedule->follow_up_type, $schedule->priority, $schedule->reason, $schedule->dai_id, $schedule->template_id);
        });
    }

    private function close(ConvertFollowUpSchedule $schedule, ScheduleStatus $target, ?string $reason, User $actor): ConvertFollowUpSchedule
    {
        $this->authorize(Convert::query()->findOrFail($schedule->convert_id), $actor);

        return DB::transaction(function () use ($schedule, $target, $reason): ConvertFollowUpSchedule {
            $locked = ConvertFollowUpSchedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if ($locked->status !== ScheduleStatus::Scheduled) {
                throw FollowUpRuleViolation::invalidTransition();
            }

            $locked->status = $target;
            $locked->reason = $reason ?? $locked->reason;
            $locked->save();

            return $locked;
        });
    }

    private function add(Convert $convert, CarbonImmutable $at, string $type, Priority $priority, ?string $reason, int $daiId, ?int $templateId = null): ConvertFollowUpSchedule
    {
        $schedule = new ConvertFollowUpSchedule;
        $schedule->convert_id = $convert->id;
        $schedule->dai_id = $daiId;
        $schedule->template_id = $templateId;
        $schedule->scheduled_at = $at;
        $schedule->follow_up_type = $type;
        $schedule->priority = $priority;
        $schedule->reason = $reason;
        $schedule->status = ScheduleStatus::Scheduled;
        $schedule->save();

        return $schedule;
    }

    private function authorize(Convert $convert, User $actor): void
    {
        if (! $actor->can('recordFollowUp', $convert)) {
            throw new AuthorizationException;
        }
    }
}
