<?php

declare(strict_types=1);

namespace App\Domain\Registration\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Certificates\Actions\CertificateWorkflow;
use App\Domain\Converts\Actions\ChangeConvertStatus;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Notifications\Services\OfficeRecipients;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Enums\ShahadahStatus;
use App\Domain\Registration\Enums\VoidRequestStatus;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Registration\Models\ShahadahVoidRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Voiding a registration and its shahadah for a data correction (07 §7.1):
 * the office's Head of Dawah asks with a reason (shahadah.request_void), a
 * Central Admin decides (shahadah.approve_void), never the requester. On
 * approval, in one transaction: registration and shahadah voided, shahadah
 * certificates revoked or rejected, the convert record closed and its
 * identity numbers released so the person can be registered again.
 * Nothing is deleted.
 */
final readonly class ShahadahVoiding
{
    public function __construct(
        private TenantContext $tenantContext,
        private IdentityRegistry $identities,
        private CertificateWorkflow $certificates,
        private ChangeConvertStatus $convertStatus,
        private OfficeRecipients $recipients,
        private AuditLogger $audit,
    ) {}

    public function request(ConvertRegistration $registration, User $actor, ?string $reason): ShahadahVoidRequest
    {
        if (! $actor->hasPermission('shahadah.request_void')) {
            throw new AuthorizationException;
        }

        $reason = blank($reason) ? null : trim((string) $reason);

        if ($reason === null) {
            throw RegistrationRuleViolation::noteRequired();
        }

        if ($registration->status === RegistrationStatus::Voided) {
            throw RegistrationRuleViolation::alreadyVoided();
        }

        $request = new ShahadahVoidRequest;
        $request->tenant_id = $registration->tenant_id;
        $request->convert_registration_id = $registration->id;
        $request->requested_by = $actor->id;
        $request->reason = $reason;
        $request->status = VoidRequestStatus::Pending;

        try {
            $request->save();
        } catch (UniqueConstraintViolationException) {
            throw RegistrationRuleViolation::voidRequestOpen();
        }

        $this->audit->record('shahadah.void_requested', $registration->loadMissing('convert')->convert, newValues: [
            'void_request_id' => $request->id,
            'registration_no' => $registration->registration_no,
            'reason' => $reason,
        ], actor: $actor);

        Notification::send($this->recipients->platformWithPermission('shahadah.approve_void', $actor->id), new OfficeAlert('shahadah_void_requested', null));

        return $request;
    }

    public function approve(ShahadahVoidRequest $request, User $actor, ?string $note = null): ShahadahVoidRequest
    {
        // Checked in Central's context, before the work runs in the office's.
        $this->authorizeDecision($actor);

        $decided = $this->tenantContext->run($request->tenant_id, fn (): ShahadahVoidRequest => $this->tenantContext->bypass(
            'shahadah void approved in Central',
            fn (): ShahadahVoidRequest => DB::transaction(fn (): ShahadahVoidRequest => $this->voidRegistration($request, $actor, $note)),
        ));

        $this->tellRequester($decided, 'shahadah_void_approved');

        return $decided;
    }

    public function reject(ShahadahVoidRequest $request, User $actor, ?string $note): ShahadahVoidRequest
    {
        $note = blank($note) ? null : trim((string) $note);

        if ($note === null) {
            throw RegistrationRuleViolation::noteRequired();
        }

        $this->authorizeDecision($actor);

        $decided = DB::transaction(function () use ($request, $actor, $note): ShahadahVoidRequest {
            $locked = $this->lockPending($request, $actor);
            $this->decide($locked, VoidRequestStatus::Rejected, $actor, $note);

            $this->audit->record('shahadah.void_rejected', newValues: ['void_request_id' => $locked->id, 'note' => $note], tenantId: $locked->tenant_id, actor: $actor);

            return $locked;
        });

        $this->tellRequester($decided, 'shahadah_void_rejected');

        return $decided;
    }

    private function voidRegistration(ShahadahVoidRequest $request, User $actor, ?string $note): ShahadahVoidRequest
    {
        $locked = $this->lockPending($request, $actor);
        $registration = ConvertRegistration::query()->lockForUpdate()->findOrFail($locked->convert_registration_id);

        if ($registration->status === RegistrationStatus::Voided) {
            throw RegistrationRuleViolation::alreadyVoided();
        }

        $reason = 'shahadah voided: '.$locked->reason;
        $convert = Convert::query()->lockForUpdate()->findOrFail($registration->convert_id);

        $registration->status = RegistrationStatus::Voided;
        $registration->save();

        ShahadahRecord::query()->where('convert_registration_id', $registration->id)->lockForUpdate()->get()
            ->each(function (ShahadahRecord $shahadah) use ($locked): void {
                $shahadah->status = ShahadahStatus::Voided;
                $shahadah->void_reason = mb_substr($locked->reason, 0, 500);
                $shahadah->save();
            });

        $certificates = $this->certificates->closeForVoidedShahadah($convert->id, $actor, $reason);

        if (! $convert->status->isTerminal()) {
            $this->convertStatus->handle($convert, ConvertStatus::RecordClosed, $actor, $reason);
        }

        $this->identities->releaseAll($convert, $actor, 'shahadah voided');
        $this->decide($locked, VoidRequestStatus::Approved, $actor, blank($note) ? null : trim((string) $note));

        $this->audit->record('shahadah.voided', $convert, newValues: [
            'void_request_id' => $locked->id,
            'registration_no' => $registration->registration_no,
            'certificates_closed' => $certificates,
        ], actor: $actor);

        return $locked;
    }

    private function authorizeDecision(User $actor): void
    {
        if (! $actor->hasPermission('shahadah.approve_void')) {
            throw new AuthorizationException;
        }
    }

    private function lockPending(ShahadahVoidRequest $request, User $actor): ShahadahVoidRequest
    {
        $locked = ShahadahVoidRequest::query()->lockForUpdate()->findOrFail($request->id);

        if ($locked->status !== VoidRequestStatus::Pending) {
            throw RegistrationRuleViolation::voidRequestDecided();
        }

        if ($locked->requested_by === $actor->id) {
            throw RegistrationRuleViolation::selfDecision();
        }

        return $locked;
    }

    private function decide(ShahadahVoidRequest $request, VoidRequestStatus $status, User $actor, ?string $note): void
    {
        $request->status = $status;
        $request->decided_by = $actor->id;
        $request->decision_note = $note;
        $request->decided_at = CarbonImmutable::now();
        $request->save();
    }

    private function tellRequester(ShahadahVoidRequest $request, string $kind): void
    {
        User::query()->find($request->requested_by)?->notify(new OfficeAlert($kind, $request->tenant_id));
    }
}
