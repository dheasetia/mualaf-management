<?php

declare(strict_types=1);

namespace App\Domain\International\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\International\Enums\ChecklistItemCode;
use App\Domain\International\Enums\ChecklistStatus;
use App\Domain\International\Enums\ItemStatus;
use App\Domain\International\Enums\OrganizationStatus;
use App\Domain\International\Enums\ReferralStatus;
use App\Domain\International\Enums\SharedField;
use App\Domain\International\Exceptions\InternationalRuleViolation;
use App\Domain\International\Models\ExternalIslamicOrganization;
use App\Domain\International\Models\InternationalReferral;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Domain\International\Models\PreDepartureChecklistItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * International follow-up referrals (10 §10.7): drafted by
 * international.referrals.create with the fields to share; ready only with
 * an active international_sharing consent covering those fields (and the
 * destination, if the consent names one), else pending consent; sent by
 * international.referrals.send after re-checking the consent; then
 * acknowledged, active, declined or closed. Sending and acknowledgment
 * tick the convert's open pre-departure checklist.
 */
final readonly class ReferralWorkflow
{
    public function __construct(private Consents $consents, private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function create(Convert $convert, array $data, User $actor): InternationalReferral
    {
        if (! $actor->hasPermission('international.referrals.create') || ! $actor->can('recordFollowUp', $convert)) {
            throw new AuthorizationException;
        }

        $scope = array_values(array_intersect(array_map('strval', (array) ($data['information_scope'] ?? [])), array_map(fn (SharedField $f): string => $f->value, SharedField::cases())));

        if ($scope === []) {
            throw InternationalRuleViolation::scopeRequired();
        }

        $organizationId = blank($data['external_organization_id'] ?? null) ? null : (int) $data['external_organization_id'];

        if ($organizationId !== null && ! ExternalIslamicOrganization::query()->whereKey($organizationId)->where('verification_status', '!=', OrganizationStatus::Inactive)->exists()) {
            throw InternationalRuleViolation::organizationUnavailable();
        }

        return DB::transaction(function () use ($convert, $data, $scope, $organizationId, $actor): InternationalReferral {
            $referral = new InternationalReferral;
            $referral->convert_id = $convert->id;
            $referral->origin_tenant_id = $convert->origin_tenant_id;
            $referral->primary_dai_id = $convert->primary_dai_id;
            $referral->destination_country_id = (int) $data['destination_country_id'];
            $referral->destination_city = blank($data['destination_city'] ?? null) ? null : (string) $data['destination_city'];
            $referral->external_organization_id = $organizationId;
            $referral->external_contact_name = blank($data['external_contact_name'] ?? null) ? null : (string) $data['external_contact_name'];
            $referral->external_contact_phone = blank($data['external_contact_phone'] ?? null) ? null : (string) $data['external_contact_phone'];
            $referral->referral_reason = (string) $data['referral_reason'];
            $referral->information_scope = $scope;
            $referral->status = ReferralStatus::Draft;
            $referral->created_by = $actor->id;
            $referral->save();

            $this->audit->record('referral.created', $convert, newValues: ['referral_id' => $referral->id, 'scope' => $scope], actor: $actor);

            return $this->checkConsent($referral, $actor);
        });
    }

    /** Ready with a covering consent, else pending consent. */
    public function checkConsent(InternationalReferral $referral, User $actor): InternationalReferral
    {
        $consent = $this->consents->covering($referral->convert_id, $referral->information_scope, $referral->destination_country_id);
        $target = $consent === null ? ReferralStatus::PendingConsent : ReferralStatus::Ready;

        if ($target === $referral->status) {
            return $referral;
        }

        return $this->move($referral, $target, $actor, fn (InternationalReferral $locked) => $locked->consent_record_id = $consent?->id);
    }

    public function send(InternationalReferral $referral, User $actor): InternationalReferral
    {
        $this->require($actor->hasPermission('international.referrals.send'));

        return DB::transaction(function () use ($referral, $actor): InternationalReferral {
            $consent = $this->consents->covering($referral->convert_id, $referral->information_scope, $referral->destination_country_id);

            if ($consent === null) {
                $this->checkConsent($referral, $actor);

                throw InternationalRuleViolation::consentMissing();
            }

            $sent = $this->move($referral, ReferralStatus::Sent, $actor, function (InternationalReferral $locked) use ($actor, $consent): void {
                $locked->consent_record_id = $consent->id;
                $locked->sent_at = CarbonImmutable::now();
                $locked->sent_by = $actor->id;
            });
            $this->tick($sent, ChecklistItemCode::ReferralSent, $actor);

            return $sent;
        });
    }

    public function acknowledge(InternationalReferral $referral, User $actor): InternationalReferral
    {
        $this->require($actor->hasPermission('international.referrals.send'));

        return DB::transaction(function () use ($referral, $actor): InternationalReferral {
            $acknowledged = $this->move($referral, ReferralStatus::Acknowledged, $actor, fn (InternationalReferral $locked) => $locked->acknowledged_at = CarbonImmutable::now());
            $this->tick($acknowledged, ChecklistItemCode::AcknowledgmentReceived, $actor);

            return $acknowledged;
        });
    }

    public function activate(InternationalReferral $referral, User $actor): InternationalReferral
    {
        $this->require($actor->hasPermission('international.referrals.send'));

        return $this->move($referral, ReferralStatus::Active, $actor);
    }

    public function decline(InternationalReferral $referral, ?string $reason, User $actor): InternationalReferral
    {
        $this->require($actor->hasPermission('international.referrals.send'));

        return $this->move($referral, ReferralStatus::Declined, $actor, fn (InternationalReferral $locked) => $locked->close_reason = $this->reason($reason));
    }

    public function close(InternationalReferral $referral, ?string $reason, User $actor): InternationalReferral
    {
        $this->require($actor->hasPermission('international.referrals.send') || ($actor->hasPermission('international.referrals.create') && $referral->created_by === $actor->id));
        $reason = $this->reason($reason);

        return $this->move($referral, ReferralStatus::Closed, $actor, function (InternationalReferral $locked) use ($reason): void {
            $locked->close_reason = $reason;
            $locked->closed_at = CarbonImmutable::now();
        });
    }

    /** Marks a checklist item done on the convert's open checklist in this office. */
    private function tick(InternationalReferral $referral, ChecklistItemCode $code, User $actor): void
    {
        $checklist = PreDepartureChecklist::query()->where('convert_id', $referral->convert_id)->where('status', ChecklistStatus::Open)->first();

        if ($checklist === null) {
            return;
        }

        $checklist->referral_id ??= $referral->id;
        $checklist->save();

        PreDepartureChecklistItem::query()->where('checklist_id', $checklist->id)->where('item_code', $code)->where('status', ItemStatus::Pending)
            ->update(['status' => ItemStatus::Done, 'done_by' => $actor->id, 'done_at' => CarbonImmutable::now(), 'updated_at' => now()]);
    }

    /** @param  (Closure(InternationalReferral): mixed)|null  $apply */
    private function move(InternationalReferral $referral, ReferralStatus $target, User $actor, ?Closure $apply = null): InternationalReferral
    {
        return DB::transaction(function () use ($referral, $target, $actor, $apply): InternationalReferral {
            $locked = InternationalReferral::query()->lockForUpdate()->findOrFail($referral->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw InternationalRuleViolation::invalidTransition($from->label(), $target->label());
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->status = $target;
            $locked->save();

            $this->audit->record('referral.status_changed', $locked, ['status' => $from->value], ['status' => $target->value], actor: $actor);

            return $locked;
        });
    }

    private function reason(?string $reason): string
    {
        return blank($reason) ? throw InternationalRuleViolation::reasonRequired() : trim((string) $reason);
    }

    private function require(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
