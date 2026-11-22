<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Exceptions\ProspectRuleViolation;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Services\ProspectRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes the profile of an open prospect. Status and da'i have their own actions.
 */
final readonly class UpdateProspect
{
    public function __construct(private ProspectRules $rules, private AuditLogger $audit) {}

    public function handle(Prospect $prospect, ProspectData $data, User $actor): Prospect
    {
        if ($prospect->status->isTerminal()) {
            throw ProspectRuleViolation::closed();
        }

        $this->rules->checkPossibleDuplicate($data, $prospect);

        return DB::transaction(function () use ($prospect, $data, $actor): Prospect {
            $this->rules->fill($prospect, $data);

            // Taken before saving (see AuditLogger::recordChanges()).
            $changes = $prospect->getDirty();
            $old = [];

            foreach (array_keys($changes) as $key) {
                $old[$key] = $prospect->getRawOriginal($key);
            }

            $prospect->save();

            if ($changes !== []) {
                $this->audit->record('prospects.updated', $prospect, oldValues: $old, newValues: $changes, actor: $actor);
            }

            return $prospect;
        });
    }
}
