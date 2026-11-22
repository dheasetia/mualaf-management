<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Models;

use App\Domain\Platform\Models\BudgetCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A budget line of a plan (08 §8.3); visible only through a plan of this
 * office. Written through PlanBudget.
 *
 * @property int $id
 * @property int $activity_plan_id
 * @property int $budget_category_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_cost
 * @property string $estimated_amount
 * @property string|null $approved_amount
 * @property string|null $actual_amount
 * @property string $variance_amount
 * @property string|null $notes
 * @property-read BudgetCategory $category
 */
final class DawahActivityBudget extends Model
{
    protected $fillable = [];

    protected $guarded = ['variance_amount'];

    protected static function booted(): void
    {
        self::addGlobalScope('through_plan', fn (Builder $builder) => $builder->whereHas('plan'));
    }

    /** @return BelongsTo<DawahActivityPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(DawahActivityPlan::class, 'activity_plan_id');
    }

    /** @return BelongsTo<BudgetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'estimated_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'variance_amount' => 'decimal:2',
        ];
    }
}
