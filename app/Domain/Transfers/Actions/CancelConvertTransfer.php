<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Exceptions\TransferRuleViolation;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Domain\Transfers\Services\TransferTimeline;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * The requesting office withdraws its request before accepting it.
 */
final readonly class CancelConvertTransfer
{
    public function __construct(private TransferTimeline $timeline, private TenantContext $tenantContext) {}

    public function handle(ConvertTransferRequest $request, User $actor, ?string $reason): ConvertTransferRequest
    {
        if (! $actor->hasPermission('converts.transfer')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($request, $actor, $reason): ConvertTransferRequest {
            $locked = ConvertTransferRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->to_tenant_id !== $this->tenantContext->requireId()) {
                throw TransferRuleViolation::notYourSide();
            }

            $this->timeline->move($locked, TransferStatus::Cancelled, $actor, 'transfer.cancelled', array_filter(['reason' => $reason]));

            return $locked;
        });
    }
}
