<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Models;

use App\Domain\Dawah\Enums\ReportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Report of a completed plan (08 §8.6); one per plan.
 *
 * @property int $id
 * @property int $activity_plan_id
 * @property int $dai_id
 * @property int $actual_participants
 * @property string $summary
 * @property string|null $achievements
 * @property string|null $challenges
 * @property string|null $follow_up_actions
 * @property string|null $actual_budget
 * @property int $new_prospects_count
 * @property int $shahadah_count
 * @property CarbonImmutable|null $submitted_at
 * @property ReportStatus $status
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $return_note
 */
final class DawahActivityReport extends Model
{
    protected $fillable = [];

    protected static function booted(): void
    {
        self::addGlobalScope('through_plan', fn (Builder $builder) => $builder->whereHas('plan'));
    }

    /** @return BelongsTo<DawahActivityPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(DawahActivityPlan::class, 'activity_plan_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actual_participants' => 'integer',
            'actual_start_at' => 'immutable_datetime',
            'actual_end_at' => 'immutable_datetime',
            'actual_budget' => 'decimal:2',
            'new_prospects_count' => 'integer',
            'shahadah_count' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'status' => ReportStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
