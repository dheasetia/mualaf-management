<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Exceptions\ConvertRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertStatusHistory;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Administrative record status (12 §12.5), always with a reason; never a
 * statement about faith. deceased and record_closed are final.
 */
final readonly class ChangeConvertStatus
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    public function handle(Convert $convert, ConvertStatus $target, User $actor, ?string $reason): Convert
    {
        $reason = blank($reason) ? null : trim((string) $reason);

        if ($reason === null) {
            throw ConvertRuleViolation::reasonRequired();
        }

        return DB::transaction(function () use ($convert, $target, $actor, $reason): Convert {
            $locked = Convert::query()->lockForUpdate()->findOrFail($convert->id);
            $from = $locked->status;

            if (! in_array($target, $from->allowedTransitions(), true)) {
                throw ConvertRuleViolation::invalidTransition($from, $target);
            }

            $locked->status = $target;
            $locked->save();

            $history = new ConvertStatusHistory;
            $history->convert_id = $locked->id;
            $history->tenant_id = $this->tenantContext->requireId();
            $history->from_status = $from;
            $history->to_status = $target;
            $history->reason = $reason;
            $history->changed_by = $actor->id;
            $history->save();

            $this->audit->record(
                'converts.status_changed',
                $locked,
                oldValues: ['status' => $from->value],
                newValues: ['status' => $target->value, 'reason' => $reason],
                actor: $actor,
            );

            return $locked;
        });
    }
}
