<?php

declare(strict_types=1);

namespace App\Domain\Registration\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Enums\VerificationStatus;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Enums\ShahadahStatus;
use App\Domain\Registration\Events\ShahadahVerified;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Second pair of eyes on a registration (shahadah.verify): never the user who
 * registered it or recorded the shahadah (segregation of duties).
 */
final readonly class VerifyShahadah
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(ConvertRegistration $registration, User $actor): ConvertRegistration
    {
        $verified = DB::transaction(function () use ($registration, $actor): ConvertRegistration {
            $locked = ConvertRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $shahadah = ShahadahRecord::query()->where('convert_registration_id', $locked->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== RegistrationStatus::PendingVerification || $shahadah->status !== ShahadahStatus::Recorded) {
                throw RegistrationRuleViolation::notPending();
            }

            if (in_array($actor->id, [$locked->registered_by, $shahadah->recorded_by], true)) {
                throw RegistrationRuleViolation::sameUserCannotVerify();
            }

            $now = CarbonImmutable::now();

            $locked->status = RegistrationStatus::Verified;
            $locked->verified_by = $actor->id;
            $locked->verified_at = $now;
            $locked->save();

            $shahadah->status = ShahadahStatus::Verified;
            $shahadah->verified_by = $actor->id;
            $shahadah->verified_at = $now;
            $shahadah->save();

            ConvertIdentifier::query()
                ->where('convert_id', $locked->convert_id)
                ->whereNull('replaced_at')
                ->where('verification_status', VerificationStatus::Unverified)
                ->update(['verification_status' => VerificationStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => $now, 'updated_at' => $now]);

            $this->audit->record('shahadah.verified', $locked->loadMissing('convert')->convert, newValues: [
                'registration_no' => $locked->registration_no,
            ], actor: $actor);

            return $locked;
        });

        ShahadahVerified::dispatch($verified->id, $verified->convert_id, $verified->tenant_id);

        return $verified;
    }
}
