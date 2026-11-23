<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\FollowUp\Enums\ActivityLevel;
use App\Domain\FollowUp\Enums\CommunityConnection;
use App\Domain\FollowUp\Enums\FollowUpState;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Events\ConvertReturnedHome;
use App\Domain\FollowUp\Exceptions\FollowUpRuleViolation;
use App\Domain\FollowUp\Models\ConvertFollowUpStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Records the convert's long-term follow-up status (10 §10.3) by someone
 * who may record follow-ups for the convert. It never touches
 * converts.status and never records apostasy (PRD §31). Becoming
 * returned_home starts the return-home follow-up and checklist.
 */
final readonly class RecordFollowUpStatus
{
    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function handle(Convert $convert, array $data, User $actor): ConvertFollowUpStatus
    {
        if (! $actor->can('recordFollowUp', $convert)) {
            throw new AuthorizationException;
        }

        if ($convert->status->isTerminal()) {
            throw FollowUpRuleViolation::convertClosed();
        }

        return DB::transaction(function () use ($convert, $data, $actor): ConvertFollowUpStatus {
            $locked = Convert::query()->lockForUpdate()->findOrFail($convert->id);
            $previous = $locked->current_follow_up_status_id === null ? null
                : ConvertFollowUpStatus::query()->whereKey($locked->current_follow_up_status_id)->value('status');

            $status = new ConvertFollowUpStatus;
            $status->convert_id = $locked->id;
            $status->primary_dai_id = $locked->primary_dai_id;
            $status->status = FollowUpState::from((string) $data['status']);
            $status->last_contact_at = blank($data['last_contact_at'] ?? null) ? null : CarbonImmutable::parse((string) $data['last_contact_at']);
            $status->last_contact_method = blank($data['last_contact_method'] ?? null) ? null : (string) $data['last_contact_method'];
            $status->last_known_country_id = blank($data['last_known_country_id'] ?? null) ? null : (int) $data['last_known_country_id'];
            $status->last_known_city = blank($data['last_known_city'] ?? null) ? null : (string) $data['last_known_city'];
            $status->islamic_activity_status = ActivityLevel::tryFrom((string) ($data['islamic_activity_status'] ?? ''));
            $status->community_connection_status = CommunityConnection::tryFrom((string) ($data['community_connection_status'] ?? ''));
            $status->next_follow_up_at = blank($data['next_follow_up_at'] ?? null) ? null : CarbonImmutable::parse((string) $data['next_follow_up_at']);
            $status->follow_up_priority = Priority::tryFrom((string) ($data['follow_up_priority'] ?? '')) ?? Priority::Normal;
            $status->notes = blank($data['notes'] ?? null) ? null : (string) $data['notes'];
            $status->recorded_by = $actor->id;
            $status->save();

            Convert::query()->whereKey($locked->id)->update(['current_follow_up_status_id' => $status->id]);
            $this->audit->record('followup.status_recorded', $locked, ['status' => $previous instanceof FollowUpState ? $previous->value : $previous], ['status' => $status->status->value], actor: $actor);

            $wasHome = $previous === FollowUpState::ReturnedHome || $previous === FollowUpState::ReturnedHome->value;

            if ($status->status === FollowUpState::ReturnedHome && ! $wasHome) {
                ConvertReturnedHome::dispatch($locked->id, $status->tenant_id, $status->last_known_country_id, $actor->id);
            }

            return $status;
        });
    }
}
