<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Services;

use App\Domain\Prospects\Data\ProspectSnapshot;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The prospect side of the shahadah registration (12 §12.2 steps 1 and 8),
 * for the Registration module, which does not use the Prospect model. Both
 * methods run inside the registration transaction.
 */
final readonly class ProspectConversion
{
    public function __construct(private ProspectStateMachine $stateMachine) {}

    /** Locks the prospect of the office in context; it must be ready for shahadah. */
    public function lockReady(int $prospectId): ProspectSnapshot
    {
        return $this->snapshotOf(Prospect::query()->lockForUpdate()->find($prospectId));
    }

    /** Without locking, to prefill the registration form. */
    public function snapshot(int $prospectId): ProspectSnapshot
    {
        return $this->snapshotOf(Prospect::query()->find($prospectId));
    }

    private function snapshotOf(?Prospect $prospect): ProspectSnapshot
    {
        if (! $prospect instanceof Prospect || $prospect->status !== ProspectStatus::ReadyForShahadah) {
            throw ProspectRuleViolation::notReadyForShahadah();
        }

        return new ProspectSnapshot(
            id: $prospect->id,
            fullName: $prospect->full_name,
            gender: $prospect->gender,
            primaryDaiId: $prospect->primary_dai_id,
            nationalityId: $prospect->nationality_id,
            nativeLanguageId: $prospect->native_language_id,
            currentReligionId: $prospect->current_religion_id,
            currentReligionOther: $prospect->current_religion_other,
            phone: $prospect->phone_encrypted,
            email: $prospect->email_encrypted,
        );
    }

    /**
     * Ready-for-shahadah prospects of the office, for the registration form: id => name.
     *
     * @return array<int, string>
     */
    public function readyOptions(): array
    {
        return Prospect::query()->where('status', ProspectStatus::ReadyForShahadah)->orderBy('full_name')->pluck('full_name', 'id')->all();
    }

    public function markConverted(int $prospectId, int $convertId, User $actor): void
    {
        $prospect = Prospect::query()->findOrFail($prospectId);
        $prospect->converted_at = CarbonImmutable::now();
        $prospect->convert_id = $convertId;

        $this->stateMachine->transition($prospect, ProspectStatus::Converted, $actor, null);
    }
}
