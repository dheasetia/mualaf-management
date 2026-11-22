<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Services;

use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Support\Identity\PhoneNumber;

final class ProspectRules
{
    /**
     * Same phone as another prospect of this office: likely the same person.
     * Saving anyway needs explicit confirmation.
     */
    public function checkPossibleDuplicate(ProspectData $data, ?Prospect $prospect = null): void
    {
        $fingerprint = PhoneNumber::fingerprint($data->phone);

        if ($fingerprint === null || $data->confirmedPossibleDuplicate) {
            return;
        }

        $existing = Prospect::query()
            ->where('phone_fingerprint', $fingerprint)
            ->when($prospect !== null, fn ($query) => $query->whereKeyNot($prospect?->id))
            ->first();

        if ($existing !== null) {
            throw ProspectRuleViolation::possibleDuplicate($existing->full_name);
        }
    }

    public function fill(Prospect $prospect, ProspectData $data): void
    {
        $prospect->full_name = $data->fullName;
        $prospect->gender = $data->gender;
        $prospect->nationality_id = $data->nationalityId;
        $prospect->native_language_id = $data->nativeLanguageId;
        $prospect->current_religion_id = $data->currentReligionId;
        $prospect->current_religion_other = $data->currentReligionOther;
        $prospect->phone_encrypted = $data->phone;
        $prospect->email_encrypted = $data->email;
        $prospect->occupation = $data->occupation;
        $prospect->residence_city_id = $data->residenceCityId;
        $prospect->residence_description = $data->residenceDescription;
        $prospect->source_type = $data->source;
        $prospect->source_description = $data->sourceDescription;
        $prospect->interest_level = $data->interestLevel;
    }
}
