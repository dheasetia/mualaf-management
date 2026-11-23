<?php

declare(strict_types=1);

namespace App\Domain\Registration\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Actions\AddConvertContact;
use App\Domain\Converts\Actions\CreateConvert;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Enums\ContactType;
use App\Domain\Converts\Exceptions\ConvertAlreadyRegistered;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Prospects\Data\ProspectSnapshot;
use App\Domain\Prospects\Services\ProspectConversion;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Data\ShahadahWitnessData;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Enums\ShahadahRecordType;
use App\Domain\Registration\Enums\ShahadahStatus;
use App\Domain\Registration\Events\ShahadahRecorded;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Registration\Models\ShahadahWitness;
use App\Domain\Registration\Services\DuplicateScreen;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Identity\Digits;
use App\Support\Identity\PhoneNumber;
use App\Support\Numbering\NumberAllocator;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registers a convert with their first shahadah (12-transactions-state-machines
 * §12.2), from a ready-for-shahadah prospect or as a walk-in. Everything is one
 * transaction, retried on deadlock; the unique key on the identity fingerprint
 * is the final guard against a second registration of the same person. The
 * witnesses (open question #2) are required and stored with the shahadah.
 */
final readonly class RecordShahadah
{
    public function __construct(
        private IdentityRegistry $identities,
        private CreateConvert $createConvert,
        private AddConvertContact $addContact,
        private ProspectConversion $prospects,
        private DaiAssignmentService $assignments,
        private NumberAllocator $numbers,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
        private DuplicateScreen $duplicates,
    ) {}

    public function handle(ShahadahRegistrationData $data, User $actor): ConvertRegistration
    {
        if ($data->shahadahDate->isAfter(CarbonImmutable::now((string) config('app.display_timezone'))->endOfDay())) {
            throw RegistrationRuleViolation::shahadahDateInFuture();
        }

        if ($data->prospectId === null && $data->primaryDaiId === null) {
            throw RegistrationRuleViolation::primaryDaiRequired();
        }

        $witnesses = $this->normalizeWitnesses($data->witnesses);

        try {
            // Pre-check for a clear message; the unique key below is the real guard.
            if ($this->identities->findActive($data->identity) !== null) {
                throw ConvertAlreadyRegistered::make();
            }

            // Outside the transaction: review cases must survive the blocked attempt.
            $this->duplicates->screen($data, $data->phone ?? ($data->prospectId === null ? null : $this->prospects->snapshot($data->prospectId)->phone), $actor);

            $registration = DB::transaction(fn (): ConvertRegistration => $this->register($data, $witnesses, $actor), attempts: 3);
        } catch (ConvertAlreadyRegistered) {
            // Written after the rollback, so the attempt stays on record (04 §4.4).
            $this->audit->record('shahadah.duplicate_blocked', newValues: [
                'identity_type' => $data->identity->type->value,
                'prospect_id' => $data->prospectId,
            ], actor: $actor);

            throw RegistrationRuleViolation::alreadyRegistered();
        }

        ShahadahRecorded::dispatch($registration->id, $registration->tenant_id);

        return $registration;
    }

    /** @param  list<ShahadahWitnessData>  $witnesses  normalised */
    private function register(ShahadahRegistrationData $data, array $witnesses, User $actor): ConvertRegistration
    {
        $tenantId = $this->tenantContext->requireId();
        $prospect = $data->prospectId === null ? null : $this->prospects->lockReady($data->prospectId);
        $profile = $this->profileFor($data, $prospect);

        $convert = $this->createConvert->handle(
            $profile,
            $prospect->primaryDaiId ?? (int) $data->primaryDaiId,
            $prospect?->id,
            $actor,
        );

        $this->identities->add($convert, $data->identity, $actor, primary: true);

        $phone = $data->phone ?? $prospect?->phone;
        $email = $data->email ?? $prospect?->email;

        if ($phone !== null) {
            $this->addContact->handle($convert, ContactType::Mobile, $phone, $actor, primary: true);
        }

        if ($email !== null) {
            $this->addContact->handle($convert, ContactType::Email, $email, $actor);
        }

        $year = $data->shahadahDate->format('Y');
        $code = Tenant::query()->whereKey($tenantId)->value('code');

        $registration = new ConvertRegistration;
        $registration->tenant_id = $tenantId;
        $registration->convert_id = $convert->id;
        $registration->registration_no = NumberAllocator::format((string) $code, 'REG', $year, $this->numbers->next($tenantId, 'registration', $year));
        $registration->registration_date = CarbonImmutable::now((string) config('app.display_timezone'))->startOfDay();
        $registration->status = RegistrationStatus::PendingVerification;
        $registration->registered_by = $actor->id;
        $registration->notes = $data->notes;
        $registration->save();

        $shahadah = new ShahadahRecord;
        $shahadah->tenant_id = $tenantId;
        $shahadah->convert_id = $convert->id;
        $shahadah->convert_registration_id = $registration->id;
        $shahadah->record_type = ShahadahRecordType::First;
        $shahadah->shahadah_date = $data->shahadahDate;
        $shahadah->shahadah_time = $data->shahadahTime;
        $shahadah->location = $data->location;
        $shahadah->city_id = $data->cityId;
        $shahadah->preacher_dai_id = $data->preacherDaiId;
        $shahadah->recorded_by = $actor->id;
        $shahadah->status = ShahadahStatus::Recorded;
        $shahadah->notes = $data->notes;
        $shahadah->save();

        foreach ($witnesses as $witness) {
            $row = new ShahadahWitness;
            $row->shahadah_record_id = $shahadah->id;
            $row->convert_id = $convert->id;
            $row->name = $witness->name;
            $row->phone_encrypted = $witness->phone;
            $row->identity_last4 = $witness->identityLast4;
            $row->relationship = $witness->relationship;
            $row->created_by = $actor->id;
            $row->save();
        }

        if ($prospect !== null) {
            $this->prospects->markConverted($prospect->id, $convert->id, $actor);
            $this->assignments->endAllFor('prospect', $prospect->id, 'converted');
        }

        $this->assignments->assignPrimary($convert, $convert->primary_dai_id, $actor);

        $this->audit->record('shahadah.recorded', $convert, newValues: [
            'registration_no' => $registration->registration_no,
            'shahadah_date' => $data->shahadahDate->toDateString(),
            'prospect_id' => $prospect?->id,
            'witnesses' => count($witnesses),
        ], actor: $actor);

        return $registration;
    }

    /** The form's profile, completed from the prospect where the form left a field empty. */
    private function profileFor(ShahadahRegistrationData $data, ?ProspectSnapshot $prospect): ConvertProfileData
    {
        $profile = $data->profile;

        if ($prospect === null) {
            return $profile;
        }

        return new ConvertProfileData(
            fullName: $profile->fullName,
            gender: $profile->gender,
            nationalityId: $profile->nationalityId,
            nameAr: $profile->nameAr,
            previousName: $profile->previousName,
            islamicName: $profile->islamicName,
            dateOfBirth: $profile->dateOfBirth,
            nativeLanguageId: $profile->nativeLanguageId ?? $prospect->nativeLanguageId,
            previousReligionId: $profile->previousReligionId ?? $prospect->currentReligionId,
            previousReligionOther: $profile->previousReligionOther ?? $prospect->currentReligionOther,
        );
    }

    /**
     * Enough distinct witnesses, each with a name and a valid phone (stored
     * as +digits); the optional identity suffix is exactly four digits.
     *
     * @param  list<ShahadahWitnessData>  $witnesses
     * @return list<ShahadahWitnessData>
     */
    private function normalizeWitnesses(array $witnesses): array
    {
        $minimum = (int) config('registration.shahadah_min_witnesses');
        $maximum = (int) config('registration.shahadah_max_witnesses');

        if (count($witnesses) < $minimum) {
            throw RegistrationRuleViolation::witnessesRequired($minimum);
        }

        if (count($witnesses) > $maximum) {
            throw RegistrationRuleViolation::tooManyWitnesses($maximum);
        }

        $normalized = [];

        foreach ($witnesses as $witness) {
            $name = trim($witness->name);
            $phone = PhoneNumber::normalize($witness->phone);
            $last4 = $witness->identityLast4 === null ? null : Digits::toAscii(trim($witness->identityLast4));

            if ($name === '' || $phone === null || ($last4 !== null && preg_match('/^\d{4}$/', $last4) !== 1)) {
                throw RegistrationRuleViolation::invalidWitness();
            }

            if (array_key_exists($phone, $normalized)) {
                throw RegistrationRuleViolation::duplicateWitness();
            }

            $relationship = $witness->relationship === null ? null : trim($witness->relationship);
            $normalized[$phone] = new ShahadahWitnessData($name, '+'.$phone, $last4, $relationship === '' ? null : $relationship);
        }

        return array_values($normalized);
    }
}
