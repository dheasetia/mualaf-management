<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Services;

use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Dawah\Enums\ParticipantType;
use App\Domain\Dawah\Models\ActivityParticipant;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Services\ProspectVisibility;
use App\Models\User;

/**
 * Names and choices of plan participants, under the same visibility as the
 * prospect and convert screens. Printed or exported lists leave out
 * high-confidentiality converts (04 §4.4, 08 §8.5).
 */
final readonly class ParticipantDirectory
{
    public function __construct(
        private ConvertAccess $converts,
        private ProspectVisibility $prospects,
        private DaiDirectory $dais,
    ) {}

    public function canInvite(ParticipantType $type, int $id, User $user): bool
    {
        return match ($type) {
            ParticipantType::Convert => ($convert = Convert::query()->find($id)) instanceof Convert && $this->converts->canSee($user, $convert),
            ParticipantType::Prospect => ($prospect = Prospect::query()->find($id)) instanceof Prospect && $this->prospects->canSee($user, $prospect),
            ParticipantType::Dai => array_key_exists($id, $this->dais->activeOptions()),
            default => false,
        };
    }

    /** @return array<int, string> */
    public function options(ParticipantType $type, User $user): array
    {
        return match ($type) {
            ParticipantType::Convert => $this->converts->visible(Convert::query(), $user)->orderBy('full_name')->limit(500)->get()
                ->mapWithKeys(fn (Convert $convert): array => [$convert->id => $this->converts->displayName($user, $convert)])->all(),
            ParticipantType::Prospect => $this->prospects->apply(Prospect::query(), $user)->orderBy('full_name')->limit(500)->pluck('full_name', 'id')->all(),
            ParticipantType::Dai => $this->dais->activeOptions(),
            default => [],
        };
    }

    public function name(ActivityParticipant $participant, User $user): string
    {
        $id = (int) $participant->participant_id;

        return match ($participant->participant_type) {
            ParticipantType::Convert => ($convert = Convert::query()->find($id)) instanceof Convert ? $this->converts->displayName($user, $convert) : '—',
            ParticipantType::Prospect => ($prospect = Prospect::query()->find($id)) instanceof Prospect && $this->prospects->canSee($user, $prospect) ? $prospect->full_name : '—',
            ParticipantType::Dai => $this->dais->names([$id])[$id] ?? '—',
            default => (string) $participant->participant_name,
        };
    }

    /**
     * Rows for a printed or exported list: name, type and attendance, without
     * high-confidentiality converts.
     *
     * @return list<array{name: string, type: string, attendance: string}>
     */
    public function printable(DawahActivityPlan $plan, User $user): array
    {
        $hidden = Convert::query()
            ->whereIn('id', $plan->participants()->where('participant_type', ParticipantType::Convert)->pluck('participant_id'))
            ->where('disclosure_risk_level', DisclosureLevel::HighConfidentiality)
            ->pluck('id')->all();

        return array_values($plan->participants()->orderBy('id')->get()
            ->reject(fn (ActivityParticipant $participant): bool => $participant->participant_type === ParticipantType::Convert && in_array($participant->participant_id, $hidden, true))
            ->map(fn (ActivityParticipant $participant): array => [
                'name' => $this->name($participant, $user),
                'type' => $participant->participant_type->label(),
                'attendance' => $participant->attendance_status->label(),
            ])->all());
    }
}
