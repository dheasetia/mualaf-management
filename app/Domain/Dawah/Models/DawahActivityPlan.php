<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Models;

use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Platform\Models\DaiActivityType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Dawah\DawahActivityPlanFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A planned dawah activity (08 §8.2). Status changes only through PlanWorkflow.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $plan_number
 * @property string $title
 * @property int $activity_type_id
 * @property string $objective
 * @property string|null $description
 * @property string|null $target_audience
 * @property int|null $expected_participants
 * @property int $proposed_by_dai_id
 * @property int $responsible_dai_id
 * @property int|null $supervisor_user_id
 * @property CarbonImmutable $planned_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int|null $city_id
 * @property string|null $location
 * @property string $estimated_budget
 * @property string|null $approved_budget
 * @property string|null $actual_budget
 * @property bool $transport_required
 * @property bool $material_required
 * @property string|null $equipment_required
 * @property PlanStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $rejection_reason
 * @property string|null $revision_note
 * @property string|null $cancellation_reason
 * @property int $created_by
 * @property-read DaiActivityType $activityType
 * @property-read DawahActivityReport|null $report
 */
#[UseFactory(DawahActivityPlanFactory::class)]
final class DawahActivityPlan extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DawahActivityPlanFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<DaiActivityType, $this> */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(DaiActivityType::class, 'activity_type_id');
    }

    /** @return HasMany<ActivityParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class, 'activity_plan_id');
    }

    /** @return HasMany<DawahActivityBudget, $this> */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(DawahActivityBudget::class, 'activity_plan_id');
    }

    /** @return HasOne<DawahActivityReport, $this> */
    public function report(): HasOne
    {
        return $this->hasOne(DawahActivityReport::class, 'activity_plan_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'planned_date' => 'immutable_date',
            'expected_participants' => 'integer',
            'estimated_budget' => 'decimal:2',
            'approved_budget' => 'decimal:2',
            'actual_budget' => 'decimal:2',
            'transport_required' => 'boolean',
            'material_required' => 'boolean',
            'status' => PlanStatus::class,
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
