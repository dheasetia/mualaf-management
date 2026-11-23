<?php

declare(strict_types=1);

namespace App\Domain\Registration\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Actions\UpdateConvert;
use App\Domain\Converts\Exceptions\ConvertAlreadyRegistered;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Registration\Data\ShahadahCorrectionData;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Events\ShahadahRecorded;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Registration\Models\ShahadahWitness;
use App\Domain\Registration\Services\ShahadahWitnesses;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Sends a returned registration back for verification with corrected data:
 * profile (UpdateConvert rules), optionally a new identity number (the old
 * one is retired; the unique key still guards), shahadah details and the
 * witnesses. The corrector, like the registrar, may not verify it.
 * The registration number and the prospect link stay.
 */
final readonly class CorrectShahadahRegistration
{
    public function __construct(
        private UpdateConvert $updateConvert,
        private IdentityRegistry $identities,
        private ShahadahWitnesses $witnesses,
        private AuditLogger $audit,
    ) {}

    public function handle(ConvertRegistration $registration, ShahadahCorrectionData $data, User $actor): ConvertRegistration
    {
        if (! $actor->hasPermission('shahadah.record')) {
            throw new AuthorizationException;
        }

        if ($data->shahadahDate->isAfter(CarbonImmutable::now((string) config('app.display_timezone'))->endOfDay())) {
            throw RegistrationRuleViolation::shahadahDateInFuture();
        }

        $witnesses = $this->witnesses->normalize($data->witnesses);

        try {
            $corrected = DB::transaction(function () use ($registration, $data, $witnesses, $actor): ConvertRegistration {
                $locked = ConvertRegistration::query()->lockForUpdate()->findOrFail($registration->id);

                if ($locked->status !== RegistrationStatus::Returned) {
                    throw RegistrationRuleViolation::notReturned();
                }

                $convert = Convert::query()->findOrFail($locked->convert_id);
                $this->updateConvert->handle($convert, $data->profile, $actor);

                if ($data->identity !== null) {
                    $this->identities->replacePrimary($convert, $data->identity, $actor, 'corrected before verification');
                }

                $shahadah = ShahadahRecord::query()->where('convert_registration_id', $locked->id)->lockForUpdate()->firstOrFail();
                $shahadah->shahadah_date = $data->shahadahDate;
                $shahadah->shahadah_time = $data->shahadahTime;
                $shahadah->location = $data->location;
                $shahadah->city_id = $data->cityId;
                $shahadah->preacher_dai_id = $data->preacherDaiId;
                $shahadah->notes = $data->notes;
                $changed = array_keys($shahadah->getDirty());
                $shahadah->save();

                ShahadahWitness::query()->where('shahadah_record_id', $shahadah->id)->delete();
                $this->witnesses->store($shahadah, $witnesses, $actor);

                $locked->status = RegistrationStatus::PendingVerification;
                $locked->corrected_by = $actor->id;
                $locked->rejection_reason = null;
                $locked->notes = $data->notes;
                $locked->save();

                $this->audit->record('shahadah.corrected', $convert, newValues: [
                    'registration_no' => $locked->registration_no,
                    'shahadah_fields' => $changed,
                    'identity_replaced' => $data->identity !== null,
                    'witnesses' => count($witnesses),
                ], actor: $actor);

                return $locked;
            }, attempts: 3);
        } catch (ConvertAlreadyRegistered) {
            throw RegistrationRuleViolation::alreadyRegistered();
        }

        ShahadahRecorded::dispatch($corrected->id, $corrected->tenant_id);

        return $corrected;
    }
}
