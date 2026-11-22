<?php

declare(strict_types=1);

namespace App\Domain\Dai\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dai\Data\DaiData;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiLanguage;
use App\Domain\Dai\Services\DaiRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes a da'i profile and languages; status has its own action.
 */
final readonly class UpdateDai
{
    public function __construct(private DaiRules $rules, private AuditLogger $audit) {}

    public function handle(Dai $dai, DaiData $data, User $actor): Dai
    {
        $this->rules->check($data, $dai);

        return DB::transaction(function () use ($dai, $data, $actor): Dai {
            $previousLanguages = DaiLanguage::query()->where('dai_id', $dai->id)->get()
                ->mapWithKeys(fn (DaiLanguage $row): array => [$row->language_id => $row->proficiency->value])
                ->all();

            $this->rules->fill($dai, $data);

            // Taken before saving: getChanges() keeps the previous save's changes
            // when nothing is dirty, and refresh() does not clear it.
            $changes = $dai->getDirty();
            $old = array_map(fn (string $key): mixed => $dai->getRawOriginal($key), array_combine(array_keys($changes), array_keys($changes)));

            $this->rules->save($dai);
            $languages = $this->rules->syncLanguages($dai, $data);

            if ($languages != $previousLanguages) {
                $old['languages'] = $previousLanguages;
                $changes['languages'] = $languages;
            }

            if ($changes !== []) {
                $this->audit->record('dai.updated', $dai, oldValues: $old, newValues: $changes, actor: $actor);
            }

            return $dai;
        });
    }
}
