<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Domain\Transfers\Scopes\TransferPartyScope;
use App\Domain\Transfers\Services\TransferTimeline;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Requests the old office has not answered within the SLA become escalated
 * for Central (12 §12.3; SLA default 7 days, open question #4). Runs from the
 * scheduler, without a user.
 */
final readonly class EscalateOverdueTransfers
{
    public function __construct(private TransferTimeline $timeline, private TenantContext $tenantContext) {}

    public function handle(int $slaDays): int
    {
        $overdue = ConvertTransferRequest::query()
            ->withoutGlobalScope(TransferPartyScope::class)
            ->whereIn('status', [TransferStatus::Pending, TransferStatus::UnderReview])
            ->where('requested_at', '<', CarbonImmutable::now()->subDays($slaDays))
            ->pluck('id');

        foreach ($overdue as $id) {
            DB::transaction(function () use ($id, $slaDays): void {
                $request = ConvertTransferRequest::query()->withoutGlobalScope(TransferPartyScope::class)->lockForUpdate()->findOrFail((int) $id);

                if (! in_array($request->status, [TransferStatus::Pending, TransferStatus::UnderReview], true)) {
                    return;
                }

                $this->tenantContext->run($request->from_tenant_id, function () use ($request, $slaDays): void {
                    $request->escalated_at = CarbonImmutable::now();
                    $request->escalation_note = "No answer within {$slaDays} days";
                    $this->timeline->move($request, TransferStatus::Escalated, null, 'transfer.escalated', ['sla_days' => $slaDays]);
                });
            });
        }

        return $overdue->count();
    }
}
