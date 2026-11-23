<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Services;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * How a transfer shows the convert: the office that holds them sees what
 * ConvertAccess allows; the requesting office sees initials only, as in the
 * national search, until the transfer completes.
 */
final readonly class TransferLabels
{
    public function __construct(private TenantContext $tenantContext, private ConvertAccess $access, private TransferTimeline $timeline) {}

    public function convertName(ConvertTransferRequest $request, User $user): string
    {
        $convert = $this->timeline->convertOf($request);

        if ($convert->current_tenant_id === $this->tenantContext->id()) {
            return $this->access->displayName($user, $convert);
        }

        return $convert->initials();
    }

    public function isIncoming(ConvertTransferRequest $request): bool
    {
        return $request->to_tenant_id === $this->tenantContext->id();
    }

    /**
     * For the convert page: completed transfers of this convert, newest first.
     *
     * @return list<ConvertTransferRequest>
     */
    public function completedFor(Convert $convert): array
    {
        return array_values(ConvertTransferRequest::query()
            ->withoutGlobalScopes()
            ->with(['fromTenant', 'toTenant'])
            ->where('convert_id', $convert->id)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->get()
            ->all());
    }
}
