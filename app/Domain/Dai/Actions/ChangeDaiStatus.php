<?php

declare(strict_types=1);

namespace App\Domain\Dai\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ChangeDaiStatus
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * A da'i who is still primary for someone cannot become inactive: their
     * prospects and converts must be reassigned first (step 8).
     */
    public function handle(Dai $dai, DaiStatus $target, User $actor, ?string $reason = null): Dai
    {
        return DB::transaction(function () use ($dai, $target, $actor, $reason): Dai {
            $locked = Dai::query()->lockForUpdate()->findOrFail($dai->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw DaiRuleViolation::invalidTransition($from->label(), $target->label());
            }

            if ($target === DaiStatus::Inactive) {
                $primaries = DaiAssignment::query()->active()
                    ->where('dai_id', $locked->id)
                    ->where('is_primary', true)
                    ->count();

                if ($primaries > 0) {
                    throw DaiRuleViolation::hasActivePrimaryAssignments($primaries);
                }
            }

            $locked->status = $target;
            $locked->save();

            $this->audit->record(
                'dai.status_changed',
                $locked,
                oldValues: ['status' => $from->value],
                newValues: array_filter(['status' => $target->value, 'reason' => $reason]),
                actor: $actor,
            );

            return $locked;
        });
    }
}
