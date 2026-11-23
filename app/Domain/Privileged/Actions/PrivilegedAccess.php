<?php

declare(strict_types=1);

namespace App\Domain\Privileged\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Notifications\Services\OfficeRecipients;
use App\Domain\Privileged\Enums\PrivilegedScope;
use App\Domain\Privileged\Enums\PrivilegedStatus;
use App\Domain\Privileged\Exceptions\PrivilegedRuleViolation;
use App\Domain\Privileged\Models\PrivilegedAccessRequest;
use App\Domain\Privileged\Services\PrivilegedConvertView;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Break-glass access to one convert (04 §4.8): a platform user with
 * privileged.request asks with a reason, scopes and a duration (15 minutes
 * to 24 hours); someone else holding privileged.approve approves (the grant
 * starts then) or rejects with a note; the requester or an approver can
 * revoke; privileged:expire ends grants. Only the requester opens an active
 * grant, and every opening is audited with privileged_grant_id.
 */
final readonly class PrivilegedAccess
{
    public const int MAX_MINUTES = 1440;

    public function __construct(
        private IdentityRegistry $identities,
        private PrivilegedConvertView $view,
        private OfficeRecipients $recipients,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
    ) {}

    /** @param  list<string>  $scopes */
    public function request(IdentifierData $identity, string $reason, array $scopes, int $minutes, User $actor): PrivilegedAccessRequest
    {
        $this->requirePlatform();
        $this->require($actor->hasPermission('privileged.request'));

        if (blank($reason)) {
            throw PrivilegedRuleViolation::reasonRequired();
        }

        $scopes = array_values(array_intersect($scopes, array_map(fn (PrivilegedScope $scope): string => $scope->value, PrivilegedScope::cases())));

        if ($scopes === []) {
            throw PrivilegedRuleViolation::scopeRequired();
        }

        if ($minutes < 15 || $minutes > self::MAX_MINUTES) {
            throw PrivilegedRuleViolation::durationInvalid();
        }

        $identifier = $this->identities->findActive($identity) ?? throw PrivilegedRuleViolation::convertNotFound();
        $tenantId = (int) $this->tenantContext->bypass('privileged access request', fn (): mixed => DB::table('converts')->where('id', $identifier->convert_id)->value('current_tenant_id'));

        $request = new PrivilegedAccessRequest;
        $request->requested_by = $actor->id;
        $request->tenant_id = $tenantId;
        $request->resource_type = 'convert';
        $request->resource_id = $identifier->convert_id;
        $request->reason = trim($reason);
        $request->requested_scope = $scopes;
        $request->requested_duration_minutes = $minutes;
        $request->status = PrivilegedStatus::Pending;
        $request->save();

        $this->audit->record('privileged.requested', $request, newValues: ['scope' => $scopes, 'minutes' => $minutes], actor: $actor);
        Notification::send($this->recipients->platformWithPermission('privileged.approve', $actor->id), new OfficeAlert('privileged_requested', null));

        return $request;
    }

    public function approve(PrivilegedAccessRequest $request, User $actor, ?string $note = null): PrivilegedAccessRequest
    {
        return $this->decide($request, $actor, function (PrivilegedAccessRequest $locked) use ($actor, $note): void {
            $locked->status = PrivilegedStatus::Active;
            $locked->approved_by = $actor->id;
            $locked->approved_at = CarbonImmutable::now();
            $locked->expires_at = CarbonImmutable::now()->addMinutes($locked->requested_duration_minutes);
            $locked->decision_note = blank($note) ? null : trim((string) $note);
        }, 'privileged.approved');
    }

    public function reject(PrivilegedAccessRequest $request, User $actor, ?string $note): PrivilegedAccessRequest
    {
        if (blank($note)) {
            throw PrivilegedRuleViolation::reasonRequired();
        }

        return $this->decide($request, $actor, function (PrivilegedAccessRequest $locked) use ($actor, $note): void {
            $locked->status = PrivilegedStatus::Rejected;
            $locked->approved_by = $actor->id;
            $locked->decision_note = trim((string) $note);
        }, 'privileged.rejected');
    }

    public function revoke(PrivilegedAccessRequest $request, User $actor): PrivilegedAccessRequest
    {
        $this->requirePlatform();
        $this->require($request->requested_by === $actor->id || $actor->hasPermission('privileged.approve'));

        return DB::transaction(function () use ($request, $actor): PrivilegedAccessRequest {
            $locked = PrivilegedAccessRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== PrivilegedStatus::Active) {
                throw PrivilegedRuleViolation::notActive();
            }

            $locked->status = PrivilegedStatus::Revoked;
            $locked->revoked_at = CarbonImmutable::now();
            $locked->revoked_by = $actor->id;
            $locked->save();

            $this->audit->record('privileged.revoked', $locked, actor: $actor, privilegedGrantId: $locked->id);

            return $locked;
        });
    }

    /**
     * The data an active grant shows; each call is audited against the grant.
     *
     * @return array<string, array<string, string|null>>
     */
    public function open(PrivilegedAccessRequest $grant, User $actor): array
    {
        $this->requirePlatform();
        $this->require($grant->requested_by === $actor->id);

        if (! $grant->isActive()) {
            throw PrivilegedRuleViolation::notActive();
        }

        $sections = $this->view->sections($grant);
        $this->audit->record('privileged.viewed', $grant, newValues: ['scope' => array_keys($sections), 'convert_id' => $grant->resource_id],
            tenantId: $grant->tenant_id, actor: $actor, privilegedGrantId: $grant->id);

        return $sections;
    }

    /** Ends grants past their expiry. */
    public function expire(): int
    {
        $expired = 0;

        PrivilegedAccessRequest::query()->where('status', PrivilegedStatus::Active)->where('expires_at', '<=', CarbonImmutable::now())
            ->each(function (PrivilegedAccessRequest $grant) use (&$expired): void {
                $grant->status = PrivilegedStatus::Expired;
                $grant->save();
                $this->audit->record('privileged.expired', $grant, privilegedGrantId: $grant->id);
                $expired++;
            });

        return $expired;
    }

    /** @param  Closure(PrivilegedAccessRequest): void  $apply */
    private function decide(PrivilegedAccessRequest $request, User $actor, Closure $apply, string $action): PrivilegedAccessRequest
    {
        $this->requirePlatform();
        $this->require($actor->hasPermission('privileged.approve'));

        if ($request->requested_by === $actor->id) {
            throw PrivilegedRuleViolation::selfApproval();
        }

        return DB::transaction(function () use ($request, $actor, $apply, $action): PrivilegedAccessRequest {
            $locked = PrivilegedAccessRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== PrivilegedStatus::Pending) {
                throw PrivilegedRuleViolation::notPending();
            }

            $apply($locked);
            $locked->save();

            $this->audit->record($action, $locked, newValues: ['note' => $locked->decision_note], actor: $actor, privilegedGrantId: $locked->id);
            User::query()->find($locked->requested_by)
                ?->notify(new OfficeAlert($action === 'privileged.approved' ? 'privileged_approved' : 'privileged_rejected', null));

            return $locked;
        });
    }

    private function requirePlatform(): void
    {
        if ($this->tenantContext->has()) {
            throw PrivilegedRuleViolation::platformOnly();
        }
    }

    private function require(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
