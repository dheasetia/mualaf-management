<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Exceptions\TransferRuleViolation;
use App\Domain\Transfers\Models\ConvertTransferEvent;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Moves a convert transfer request along its state machine with an
 * append-only event and an audit entry. Callers hold the request row lock.
 */
final readonly class TransferTimeline
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function move(ConvertTransferRequest $request, TransferStatus $target, ?User $actor, string $auditAction, array $metadata = []): void
    {
        $from = $request->status;

        if (! $from->canTransitionTo($target)) {
            throw TransferRuleViolation::invalidTransition($from->label(), $target->label());
        }

        $request->status = $target;
        $request->save();

        $this->record($request, $from, $target, $actor, $target->value, $metadata);
        $this->audit($request, $auditAction, $actor, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function audit(ConvertTransferRequest $request, string $action, ?User $actor, array $metadata = []): void
    {
        $this->audit->record($action, $this->convertOf($request), newValues: [
            'transfer_id' => $request->id,
            'from_tenant_id' => $request->from_tenant_id,
            'to_tenant_id' => $request->to_tenant_id,
            'status' => $request->status->value,
            ...$metadata,
        ], tenantId: $this->tenantContext->id(), actor: $actor);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(ConvertTransferRequest $request, ?TransferStatus $from, ?TransferStatus $to, ?User $actor, string $type, array $metadata = []): void
    {
        $event = new ConvertTransferEvent;
        $event->transfer_request_id = $request->id;
        $event->actor_id = $actor?->id;
        $event->tenant_id = $this->tenantContext->id();
        $event->event_type = $type;
        $event->from_status = $from;
        $event->to_status = $to;
        $event->metadata = $metadata;
        $event->save();
    }

    /** The convert may sit in the other office: read it through an audited bypass. */
    public function convertOf(ConvertTransferRequest $request): Convert
    {
        return $this->tenantContext->bypass('transfer', fn (): Convert => Convert::query()->findOrFail($request->convert_id));
    }
}
