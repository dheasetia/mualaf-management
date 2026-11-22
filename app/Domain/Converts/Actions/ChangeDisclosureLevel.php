<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Exceptions\ConvertRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * converts.set_disclosure with a reason, audited (04-identity-privacy §4.6).
 */
final readonly class ChangeDisclosureLevel
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Convert $convert, DisclosureLevel $level, User $actor, ?string $reason): Convert
    {
        $reason = blank($reason) ? null : trim((string) $reason);

        if ($reason === null) {
            throw ConvertRuleViolation::reasonRequired();
        }

        return DB::transaction(function () use ($convert, $level, $actor, $reason): Convert {
            $locked = Convert::query()->lockForUpdate()->findOrFail($convert->id);
            $from = $locked->disclosure_risk_level;

            if ($from === $level) {
                return $locked;
            }

            $locked->disclosure_risk_level = $level;
            $locked->save();

            $this->audit->record(
                'converts.disclosure_changed',
                $locked,
                oldValues: ['disclosure_risk_level' => $from->value],
                newValues: ['disclosure_risk_level' => $level->value, 'reason' => $reason],
                actor: $actor,
            );

            return $locked;
        });
    }
}
