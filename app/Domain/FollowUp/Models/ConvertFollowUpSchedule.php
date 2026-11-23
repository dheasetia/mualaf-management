<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Models;

use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\FollowUp\ConvertFollowUpScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A planned follow-up for a da'i (10 §10.4). Overdue = scheduled and past.
 *
 * @property int $id
 * @property int $convert_id
 * @property int $tenant_id
 * @property int $dai_id
 * @property int|null $template_id
 * @property CarbonImmutable $scheduled_at
 * @property string $follow_up_type
 * @property Priority $priority
 * @property string|null $reason
 * @property ScheduleStatus $status
 * @property CarbonImmutable|null $completed_at
 * @property string|null $outcome
 * @property int|null $long_term_follow_up_id
 */
#[UseFactory(ConvertFollowUpScheduleFactory::class)]
final class ConvertFollowUpSchedule extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ConvertFollowUpScheduleFactory> */
    use HasFactory;

    protected $fillable = [];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', ScheduleStatus::Scheduled)->where('scheduled_at', '<', CarbonImmutable::now());
    }

    public function isOverdue(): bool
    {
        return $this->status === ScheduleStatus::Scheduled && $this->scheduled_at->isPast();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'priority' => Priority::class,
            'status' => ScheduleStatus::class,
            'completed_at' => 'immutable_datetime',
        ];
    }
}
