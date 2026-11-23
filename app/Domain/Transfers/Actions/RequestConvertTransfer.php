<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Exceptions\TransferRuleViolation;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Domain\Transfers\Services\TransferTimeline;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The new office asks the convert's current office to hand them over
 * (12 §12.3). The convert is found by identity, as in the national search,
 * so the requesting office never needs the convert's id.
 */
final readonly class RequestConvertTransfer
{
    /** After a rejection, the same office may ask again only after this many days. */
    public const int REQUEST_AGAIN_AFTER_DAYS = 30;

    public function __construct(
        private IdentityRegistry $identities,
        private TransferTimeline $timeline,
        private TenantContext $tenantContext,
    ) {}

    public function handle(IdentifierData $identity, string $reason, User $actor): ConvertTransferRequest
    {
        if (! $actor->hasPermission('converts.transfer')) {
            throw new AuthorizationException;
        }

        $toTenantId = $this->tenantContext->requireId();
        $identifier = $this->identities->findActive($identity) ?? throw TransferRuleViolation::notFound();
        $convert = $identifier->convert;

        if ($convert->current_tenant_id === $toTenantId) {
            throw TransferRuleViolation::sameOffice();
        }

        $recentlyRejected = ConvertTransferRequest::query()
            ->where('convert_id', $convert->id)
            ->where('to_tenant_id', $toTenantId)
            ->where('status', TransferStatus::Rejected)
            ->where('reviewed_at', '>', CarbonImmutable::now()->subDays(self::REQUEST_AGAIN_AFTER_DAYS))
            ->exists();

        if ($recentlyRejected) {
            throw TransferRuleViolation::recentlyRejected(self::REQUEST_AGAIN_AFTER_DAYS);
        }

        try {
            return DB::transaction(function () use ($convert, $toTenantId, $reason, $actor): ConvertTransferRequest {
                $request = new ConvertTransferRequest;
                $request->convert_id = $convert->id;
                $request->from_tenant_id = $convert->current_tenant_id;
                $request->to_tenant_id = $toTenantId;
                $request->requested_by = $actor->id;
                $request->reason = trim($reason);
                $request->requested_at = CarbonImmutable::now();
                $request->status = TransferStatus::Pending;
                $request->save();

                $this->timeline->record($request, null, TransferStatus::Pending, $actor, 'requested', ['reason' => $request->reason]);
                $this->timeline->audit($request, 'transfer.requested', $actor);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw TransferRuleViolation::alreadyActive();
        }
    }
}
