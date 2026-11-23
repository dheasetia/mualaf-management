<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertStatusHistory;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Inserts the convert record for the shahadah workflow only (RecordShahadah
 * runs it inside its transaction); there is no other way to create a convert.
 * Origin and current office are the office in context.
 */
final readonly class CreateConvert
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    public function handle(ConvertProfileData $profile, int $primaryDaiId, ?int $prospectId, User $actor): Convert
    {
        $convert = new Convert;
        $convert->full_name = $profile->fullName;
        $convert->name_ar = $profile->nameAr;
        $convert->previous_name = $profile->previousName;
        $convert->islamic_name = $profile->islamicName;
        $convert->gender = $profile->gender;
        $convert->date_of_birth = $profile->dateOfBirth;
        $convert->nationality_id = $profile->nationalityId;
        $convert->native_language_id = $profile->nativeLanguageId;
        $convert->previous_religion_id = $profile->previousReligionId;
        $convert->previous_religion_other = $profile->previousReligionOther;
        $convert->primary_dai_id = $primaryDaiId;
        $convert->prospect_id = $prospectId;
        $convert->status = ConvertStatus::Active;
        $convert->created_by = $actor->id;
        $convert->save();

        $history = new ConvertStatusHistory;
        $history->convert_id = $convert->id;
        $history->tenant_id = $this->tenantContext->requireId();
        $history->from_status = null;
        $history->to_status = ConvertStatus::Active;
        $history->changed_by = $actor->id;
        $history->save();

        $this->audit->record('converts.created', $convert, newValues: $convert->getAttributes(), actor: $actor);

        return $convert;
    }
}
