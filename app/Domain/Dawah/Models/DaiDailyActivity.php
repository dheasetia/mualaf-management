<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Models;

use App\Domain\Dawah\Enums\ActivityStatus;
use App\Domain\Platform\Models\DaiActivityType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Dawah\DaiDailyActivityFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A da'i's daily activity (08 §8.1). Written through RecordDailyActivity.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $dai_id
 * @property CarbonImmutable $activity_date
 * @property int $activity_type_id
 * @property string $title
 * @property string|null $description
 * @property string|null $started_at
 * @property string|null $ended_at
 * @property int|null $duration_minutes
 * @property int|null $related_prospect_id
 * @property int|null $related_convert_id
 * @property int|null $related_activity_plan_id
 * @property string|null $output_quantity
 * @property string|null $output_unit
 * @property ActivityStatus $status
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $rejection_note
 * @property-read DaiActivityType $activityType
 */
#[UseFactory(DaiDailyActivityFactory::class)]
final class DaiDailyActivity extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DaiDailyActivityFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return BelongsTo<DaiActivityType, $this> */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(DaiActivityType::class, 'activity_type_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activity_date' => 'immutable_date',
            'duration_minutes' => 'integer',
            'output_quantity' => 'decimal:2',
            'status' => ActivityStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
