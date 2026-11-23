<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Documents\Actions\Attachments;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Exceptions\DocumentRuleViolation;
use App\Domain\Documents\Services\FileSafety;
use App\Domain\International\Actions\Consents;
use App\Domain\International\Enums\ConsentType;
use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Enums\RequirementStatus;
use App\Domain\Pilgrimage\Exceptions\PilgrimageRuleViolation;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Pilgrimage\Models\PilgrimageRegistrationRequirement;
use App\Domain\Pilgrimage\Services\EligibilityRules;
use App\Domain\Pilgrimage\Services\PilgrimageEvents;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Pilgrim registrations (10 §10.2, 12 §12.5):
 * - registered by pilgrimage.register while the program is open and before
 *   its deadline, once per convert and program;
 * - eligibility checked by pilgrimage.check_eligibility against the rules
 *   and the required documents (fulfilled or waived);
 * - approved by pilgrimage.approve (not the registrar) while a seat is free,
 *   counted on the locked program row, else waitlisted;
 * - confirmed by the program manager; withdrawing needs a reason.
 */
final readonly class RegistrationWorkflow
{
    public function __construct(
        private EligibilityRules $rules,
        private Attachments $attachments,
        private FileSafety $safety,
        private ManagePrograms $programs,
        private ConvertAccess $converts,
        private PilgrimageEvents $events,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
        private Consents $consents,
    ) {}

    public function register(PilgrimageProgram $program, Convert $convert, User $actor): PilgrimageRegistration
    {
        $this->require($actor->hasPermission('pilgrimage.register'));

        $today = CarbonImmutable::now()->setTimezone((string) config('app.display_timezone'))->toDateString();

        if ($program->status !== ProgramStatus::Open || $program->registration_deadline->toDateString() < $today) {
            throw PilgrimageRuleViolation::registrationClosed();
        }

        if (! $this->converts->canSee($actor, $convert) || $convert->status->isTerminal()) {
            throw PilgrimageRuleViolation::convertUnavailable();
        }

        try {
            return DB::transaction(function () use ($program, $convert, $actor): PilgrimageRegistration {
                $registration = new PilgrimageRegistration;
                $registration->program_id = $program->id;
                $registration->convert_id = $convert->id;
                $registration->status = RegistrationStatus::Applied;
                $registration->registered_by = $actor->id;
                $registration->save();

                foreach ($program->requirements()->pluck('id') as $requirementId) {
                    $row = new PilgrimageRegistrationRequirement;
                    $row->registration_id = $registration->id;
                    $row->requirement_id = (int) $requirementId;
                    $row->status = RequirementStatus::Pending;
                    $row->save();
                }

                $this->events->record($program->id, $registration->id, $actor, 'registered');
                $this->audit->record('pilgrimage.registered', $convert, newValues: ['program_id' => $program->id], actor: $actor);

                return $registration;
            });
        } catch (UniqueConstraintViolationException) {
            throw PilgrimageRuleViolation::alreadyRegistered();
        }
    }

    /** Attaches the document for a requirement; it still needs checking. */
    public function attach(PilgrimageRegistrationRequirement $row, string $path, string $originalName, User $actor): PilgrimageRegistrationRequirement
    {
        $this->require($actor->hasPermission('pilgrimage.register'));
        $registration = $row->registration()->firstOrFail();
        $tenant = Tenant::query()->findOrFail($this->tenantContext->requireId());

        $row->document_id = $this->attachments->store($registration, DocumentType::Other, "tenants/{$tenant->uuid}/pilgrimage/{$registration->program_id}/{$registration->id}", $path, $originalName, $actor);
        $row->status = RequirementStatus::Pending;
        $row->save();

        return $row;
    }

    public function fulfil(PilgrimageRegistrationRequirement $row, User $actor): PilgrimageRegistrationRequirement
    {
        $this->require($actor->hasPermission('pilgrimage.check_eligibility'));

        return $this->settle($row, RequirementStatus::Fulfilled, null, $actor);
    }

    public function waive(PilgrimageRegistrationRequirement $row, ?string $note, User $actor): PilgrimageRegistrationRequirement
    {
        $this->require($actor->hasPermission('pilgrimage.check_eligibility'));

        return $this->settle($row, RequirementStatus::Waived, blank($note) ? throw PilgrimageRuleViolation::reasonRequired() : trim((string) $note), $actor);
    }

    public function checkEligibility(PilgrimageRegistration $registration, User $actor): PilgrimageRegistration
    {
        $this->require($actor->hasPermission('pilgrimage.check_eligibility'));
        $program = PilgrimageProgram::query()->findOrFail($registration->program_id);
        $failures = $this->rules->failures($program, $registration->convert_id);
        $pending = $registration->requirements()->where('status', RequirementStatus::Pending)
            ->whereHas('requirement', fn ($query) => $query->where('required', true))->count();
        $missing = array_values(array_filter([
            $pending > 0 ? (string) __('pilgrimage.eligibility.documents_pending', ['count' => $pending]) : null,
            $this->consents->activeOf($registration->convert_id, ConsentType::PilgrimageParticipation) === null ? (string) __('pilgrimage.eligibility.consent_missing') : null,
        ]));

        $target = match (true) {
            $failures !== [] => RegistrationStatus::Ineligible,
            $missing !== [] => RegistrationStatus::EligibilityChecked,
            default => RegistrationStatus::Eligible,
        };

        return $this->move($registration, $target, $actor, function (PilgrimageRegistration $locked) use ($actor, $failures, $missing): void {
            $locked->eligibility_checked_by = $actor->id;
            $locked->eligibility_checked_at = CarbonImmutable::now();
            $locked->eligibility_notes = $failures !== [] ? implode("\n", $failures) : ($missing !== [] ? implode("\n", $missing) : null);
        });
    }

    /** Approves into a free seat, or waitlists when the program is full. */
    public function approve(PilgrimageRegistration $registration, User $actor): PilgrimageRegistration
    {
        $this->require($actor->hasPermission('pilgrimage.approve'));

        if ($registration->registered_by === $actor->id) {
            throw PilgrimageRuleViolation::selfApproval();
        }

        // 10 §10.8: taking part needs the convert's own, active consent; the approval records which one.
        $consent = $this->consents->activeOf($registration->convert_id, ConsentType::PilgrimageParticipation)
            ?? throw PilgrimageRuleViolation::consentMissing();

        return DB::transaction(function () use ($registration, $actor, $consent): PilgrimageRegistration {
            $program = PilgrimageProgram::query()->lockForUpdate()->findOrFail($registration->program_id);
            $hasSeat = $this->programs->seatsTaken($program) < $program->capacity;
            $current = PilgrimageRegistration::query()->findOrFail($registration->id)->status;

            if (! $hasSeat && $current === RegistrationStatus::Waitlisted) {
                throw PilgrimageRuleViolation::programFull();
            }

            return $this->move($registration, $hasSeat ? RegistrationStatus::Approved : RegistrationStatus::Waitlisted, $actor, function (PilgrimageRegistration $locked) use ($actor, $hasSeat, $consent): void {
                $locked->consent_record_id = $consent->id;

                if ($hasSeat) {
                    $locked->approved_by = $actor->id;
                    $locked->approved_at = CarbonImmutable::now();
                }
            });
        });
    }

    public function confirm(PilgrimageRegistration $registration, User $actor): PilgrimageRegistration
    {
        $this->require($actor->hasPermission('pilgrimage.programs.manage'));

        return $this->move($registration, RegistrationStatus::Confirmed, $actor);
    }

    public function withdraw(PilgrimageRegistration $registration, ?string $reason, User $actor): PilgrimageRegistration
    {
        $this->require($actor->hasPermission('pilgrimage.programs.manage') || $actor->hasPermission('pilgrimage.register'));
        $reason = blank($reason) ? throw PilgrimageRuleViolation::reasonRequired() : trim((string) $reason);

        return DB::transaction(function () use ($registration, $reason, $actor): PilgrimageRegistration {
            $withdrawn = $this->move($registration, RegistrationStatus::Withdrawn, $actor, fn (PilgrimageRegistration $locked) => $locked->withdrawal_reason = $reason, ['reason' => $reason]);
            DB::table('pilgrimage_group_members')->where('pilgrimage_registration_id', $withdrawn->id)->delete();

            return $withdrawn;
        });
    }

    /**
     * A withdrawn pilgrimage consent ends every registration that has not
     * departed yet and rests on it (reason consent_withdrawn), freeing seats.
     * Called for the user who withdrew the consent; that is the authority.
     */
    public function withdrawForConsent(int $consentId, User $actor): int
    {
        $registrations = PilgrimageRegistration::query()->where('consent_record_id', $consentId)->get()
            ->filter(fn (PilgrimageRegistration $registration): bool => $registration->status->canTransitionTo(RegistrationStatus::Withdrawn));

        foreach ($registrations as $registration) {
            DB::transaction(function () use ($registration, $actor): void {
                $withdrawn = $this->move($registration, RegistrationStatus::Withdrawn, $actor, fn (PilgrimageRegistration $locked) => $locked->withdrawal_reason = 'consent_withdrawn', ['reason' => 'consent_withdrawn']);
                DB::table('pilgrimage_group_members')->where('pilgrimage_registration_id', $withdrawn->id)->delete();
            });
        }

        return $registrations->count();
    }

    /** Signed short-lived link to a requirement's document (pilgrimage.view). */
    public function documentUrl(PilgrimageRegistrationRequirement $row, User $actor): string
    {
        $this->require($actor->hasPermission('pilgrimage.view'));

        return $this->attachments->downloadUrl($row->registration()->firstOrFail(), $row->document_id ?? abort(404), $actor);
    }

    private function settle(PilgrimageRegistrationRequirement $row, RequirementStatus $status, ?string $note, User $actor): PilgrimageRegistrationRequirement
    {
        if ($status === RequirementStatus::Fulfilled && $row->document_id === null) {
            throw PilgrimageRuleViolation::notEditable();
        }

        if ($status === RequirementStatus::Fulfilled && ! $this->safety->isSafe($row->document_id)) {
            throw DocumentRuleViolation::notScanned();
        }

        $row->status = $status;
        $row->note = $note;
        $row->verified_by = $actor->id;
        $row->verified_at = CarbonImmutable::now();
        $row->save();

        $registration = $row->registration()->firstOrFail();
        $this->events->record($registration->program_id, $registration->id, $actor, 'requirement_'.$status->value, ['requirement_id' => $row->requirement_id]);

        return $row;
    }

    /**
     * @param  (Closure(PilgrimageRegistration): mixed)|null  $apply
     * @param  array<string, mixed>  $details
     */
    private function move(PilgrimageRegistration $registration, RegistrationStatus $target, User $actor, ?Closure $apply = null, array $details = []): PilgrimageRegistration
    {
        return DB::transaction(function () use ($registration, $target, $actor, $apply, $details): PilgrimageRegistration {
            $locked = PilgrimageRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw PilgrimageRuleViolation::invalidTransition($from->label(), $target->label());
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->status = $target;
            $locked->save();

            $this->events->record($locked->program_id, $locked->id, $actor, 'status_changed', ['from' => $from->value, 'to' => $target->value, ...$details]);
            $this->audit->record('pilgrimage.status_changed', $locked, ['status' => $from->value], ['status' => $target->value, ...$details], actor: $actor);

            return $locked;
        });
    }

    private function require(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
