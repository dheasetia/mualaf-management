<?php

declare(strict_types=1);

namespace App\Domain\Dai\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dai\Data\DaiData;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Services\DaiRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registers a da'i in the office in context (status active).
 */
final readonly class CreateDai
{
    public function __construct(private DaiRules $rules, private AuditLogger $audit) {}

    public function handle(DaiData $data, User $actor): Dai
    {
        $this->rules->check($data);

        return DB::transaction(function () use ($data, $actor): Dai {
            $dai = new Dai;
            $this->rules->fill($dai, $data);
            $this->rules->save($dai);
            $languages = $this->rules->syncLanguages($dai, $data);

            $this->audit->record(
                'dai.created',
                $dai,
                newValues: [...$dai->getAttributes(), 'languages' => $languages],
                actor: $actor,
            );

            return $dai;
        });
    }
}
