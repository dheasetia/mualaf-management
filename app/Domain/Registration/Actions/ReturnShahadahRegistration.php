<?php

declare(strict_types=1);

namespace App\Domain\Registration\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Notifications\Contracts\OfficeLinks;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * The verifier's "no" (decided 24-11-2026): a pending registration goes back
 * to the Registration Officer with a note, to be corrected and sent again.
 * Same segregation of duties as VerifyShahadah. The registering user is told.
 */
final readonly class ReturnShahadahRegistration
{
    public function __construct(private AuditLogger $audit, private OfficeLinks $links) {}

    public function handle(ConvertRegistration $registration, User $actor, ?string $note): ConvertRegistration
    {
        if (! $actor->hasPermission('shahadah.verify')) {
            throw new AuthorizationException;
        }

        $note = blank($note) ? null : trim((string) $note);

        if ($note === null) {
            throw RegistrationRuleViolation::noteRequired();
        }

        $returned = DB::transaction(function () use ($registration, $actor, $note): ConvertRegistration {
            $locked = ConvertRegistration::query()->with('shahadah')->lockForUpdate()->findOrFail($registration->id);

            if ($locked->status !== RegistrationStatus::PendingVerification) {
                throw RegistrationRuleViolation::notPending();
            }

            if (in_array($actor->id, [$locked->registered_by, $locked->corrected_by, $locked->shahadah?->recorded_by], true)) {
                throw RegistrationRuleViolation::sameUserCannotVerify();
            }

            $locked->status = RegistrationStatus::Returned;
            $locked->rejection_reason = $note;
            $locked->save();

            $this->audit->record('shahadah.returned', $locked->loadMissing('convert')->convert, newValues: [
                'registration_no' => $locked->registration_no,
                'note' => $note,
            ], actor: $actor);

            return $locked;
        });

        $registrar = User::query()->find($returned->corrected_by ?? $returned->registered_by);
        $registrar?->notify(new OfficeAlert('shahadah_returned', $returned->tenant_id,
            url: $this->links->shahadahRegistrations(Tenant::query()->findOrFail($returned->tenant_id))));

        return $returned;
    }
}
