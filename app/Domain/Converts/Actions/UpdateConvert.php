<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Exceptions\ConvertRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edits the profile of a convert. Office, status, disclosure and da'i have
 * their own actions; previous religion changes only for converts.view_sensitive.
 */
final readonly class UpdateConvert
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Convert $convert, ConvertProfileData $data, User $actor): Convert
    {
        if ($convert->status->isTerminal()) {
            throw ConvertRuleViolation::closed();
        }

        return DB::transaction(function () use ($convert, $data, $actor): Convert {
            $convert->full_name = $data->fullName;
            $convert->name_ar = $data->nameAr;
            $convert->previous_name = $data->previousName;
            $convert->islamic_name = $data->islamicName;
            $convert->gender = $data->gender;
            $convert->date_of_birth = $data->dateOfBirth;
            $convert->nationality_id = $data->nationalityId;
            $convert->native_language_id = $data->nativeLanguageId;

            if ($actor->hasPermission('converts.view_sensitive')) {
                $convert->previous_religion_id = $data->previousReligionId;
                $convert->previous_religion_other = $data->previousReligionOther;
            }

            $changes = $convert->getDirty();
            $old = [];

            foreach (array_keys($changes) as $key) {
                $old[$key] = $convert->getRawOriginal($key);
            }

            $convert->save();

            if ($changes !== []) {
                $this->audit->record('converts.updated', $convert, oldValues: $old, newValues: $changes, actor: $actor);
            }

            return $convert;
        });
    }
}
