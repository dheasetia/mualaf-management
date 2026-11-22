<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Actions;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Dawah\Enums\MaterialStatus;
use App\Domain\Dawah\Exceptions\DawahRuleViolation;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Domain\Dawah\Models\DawahMaterialDistribution;
use App\Domain\Dawah\Services\DawahActors;
use App\Domain\Prospects\Models\Prospect;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * A da'i hands out approved material (materials.distribute), optionally at
 * a plan of this office or to their own prospect or convert. Stock of an
 * office's printed material is decremented under lock and never goes
 * below zero.
 */
final readonly class DistributeMaterial
{
    public function __construct(private DawahActors $actors, private DaiAssignmentService $assignments) {}

    /** @param  array{quantity?: int|string|null, activity_plan_id?: int|string|null, prospect_id?: int|string|null, convert_id?: int|string|null, notes?: string|null}  $data */
    public function handle(DawahMaterial $material, array $data, User $actor): DawahMaterialDistribution
    {
        if (! $actor->hasPermission('materials.distribute')) {
            throw new AuthorizationException;
        }

        $daiId = $this->actors->daiId($actor) ?? throw DawahRuleViolation::noDaiProfile();
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $planId = blank($data['activity_plan_id'] ?? null) ? null : (int) $data['activity_plan_id'];
        $prospectId = blank($data['prospect_id'] ?? null) ? null : (int) $data['prospect_id'];
        $convertId = blank($data['convert_id'] ?? null) ? null : (int) $data['convert_id'];

        if ($planId !== null && ! DawahActivityPlan::query()->whereKey($planId)->exists()) {
            throw DawahRuleViolation::relatedUnavailable();
        }

        if ($prospectId !== null && ! (($prospect = Prospect::query()->find($prospectId)) instanceof Prospect && $this->assignments->isAssigned($prospect, $daiId))) {
            throw DawahRuleViolation::relatedUnavailable();
        }

        if ($convertId !== null && ! (($convert = Convert::query()->find($convertId)) instanceof Convert && $this->assignments->isAssigned($convert, $daiId))) {
            throw DawahRuleViolation::relatedUnavailable();
        }

        return DB::transaction(function () use ($material, $daiId, $quantity, $planId, $prospectId, $convertId, $data): DawahMaterialDistribution {
            $locked = DawahMaterial::query()->lockForUpdate()->findOrFail($material->id);

            if ($locked->status !== MaterialStatus::Approved) {
                throw DawahRuleViolation::materialNotDistributable();
            }

            if (! $locked->isNational() && $locked->stock_quantity !== null) {
                if ($locked->stock_quantity < $quantity) {
                    throw DawahRuleViolation::insufficientStock();
                }

                $locked->stock_quantity -= $quantity;
                $locked->save();
            }

            $distribution = new DawahMaterialDistribution;
            $distribution->material_id = $locked->id;
            $distribution->dai_id = $daiId;
            $distribution->activity_plan_id = $planId;
            $distribution->prospect_id = $prospectId;
            $distribution->convert_id = $convertId;
            $distribution->quantity = $quantity;
            $distribution->distributed_at = CarbonImmutable::now();
            $distribution->notes = blank($data['notes'] ?? null) ? null : (string) $data['notes'];
            $distribution->save();

            return $distribution;
        });
    }
}
