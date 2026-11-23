<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Events\TransferCompleted;
use App\Domain\Transfers\Exceptions\TransferRuleViolation;
use App\Domain\Transfers\Models\ConvertTransferHistory;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Domain\Transfers\Services\TransferTimeline;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * The new office accepts an approved transfer with the convert's new primary
 * da'i, and the transfer completes in the same transaction (12 §12.3):
 * locks the convert, then the request; checks the request is accepted and
 * the convert still belongs to the approving office, so it cannot complete
 * twice (critical test #3); moves the convert; ends the old office's
 * assignments and assigns the new da'i; records history.
 *
 * origin_tenant_id is never touched. Enrollments and follow-up schedules of
 * the old office are closed by listeners once those modules exist.
 */
final readonly class AcceptConvertTransfer
{
    public function __construct(
        private TransferTimeline $timeline,
        private DaiAssignmentService $assignments,
        private TenantContext $tenantContext,
    ) {}

    public function handle(ConvertTransferRequest $request, int $newPrimaryDaiId, User $actor): ConvertTransferRequest
    {
        if (! $actor->hasPermission('converts.transfer')) {
            throw new AuthorizationException;
        }

        $toTenantId = $this->tenantContext->requireId();

        $completed = DB::transaction(function () use ($request, $newPrimaryDaiId, $actor, $toTenantId): ConvertTransferRequest {
            $convert = $this->tenantContext->bypass('transfer completion', fn (): Convert => Convert::query()->lockForUpdate()->findOrFail($request->convert_id));
            $locked = ConvertTransferRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->to_tenant_id !== $toTenantId) {
                throw TransferRuleViolation::notYourSide();
            }

            if ($locked->status !== TransferStatus::Approved) {
                throw TransferRuleViolation::invalidTransition($locked->status->label(), TransferStatus::Accepted->label());
            }

            if ($convert->current_tenant_id !== $locked->from_tenant_id) {
                throw TransferRuleViolation::officeChanged();
            }

            $now = CarbonImmutable::now();
            $previousDaiId = $convert->primary_dai_id;

            $locked->accepted_by = $actor->id;
            $locked->accepted_at = $now;
            $locked->new_primary_dai_id = $newPrimaryDaiId;
            $this->timeline->move($locked, TransferStatus::Accepted, $actor, 'transfer.accepted', ['new_primary_dai_id' => $newPrimaryDaiId]);

            $convert->moveToOffice($toTenantId);
            $this->assignments->endAllFor('convert', $convert->id, 'transferred');
            // Checks the da'i is active and belongs to the new office.
            $this->assignments->assignPrimary($convert, $newPrimaryDaiId, $actor, 'transfer');

            $history = new ConvertTransferHistory;
            $history->convert_id = $convert->id;
            $history->transfer_request_id = $locked->id;
            $history->from_tenant_id = $locked->from_tenant_id;
            $history->to_tenant_id = $toTenantId;
            $history->approved_by = (int) $locked->reviewed_by;
            $history->accepted_by = $actor->id;
            $history->previous_primary_dai_id = $previousDaiId;
            $history->new_primary_dai_id = $newPrimaryDaiId;
            $history->transferred_at = $now;
            $history->reason = $locked->reason;
            $history->save();

            $locked->completed_at = $now;
            $this->timeline->move($locked, TransferStatus::Completed, $actor, 'transfer.completed');

            return $locked;
        }, attempts: 3);

        TransferCompleted::dispatch($completed->id, $completed->convert_id, $completed->from_tenant_id, $completed->to_tenant_id);

        return $completed;
    }
}
