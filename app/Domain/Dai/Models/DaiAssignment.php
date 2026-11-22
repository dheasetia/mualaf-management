<?php

declare(strict_types=1);

namespace App\Domain\Dai\Models;

use App\Domain\Dai\Enums\AssignmentType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Dai\DaiAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A da'i responsible for a prospect or convert (07-data-model-lifecycle §7.6).
 * Written only by DaiAssignmentService (implementation step 8).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $dai_id
 * @property string $subject_type
 * @property int $subject_id
 * @property AssignmentType $assignment_type
 * @property bool $is_primary
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $ended_at
 * @property string|null $end_reason
 * @property int $assigned_by
 */
#[UseFactory(DaiAssignmentFactory::class)]
final class DaiAssignment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DaiAssignmentFactory> */
    use HasFactory;

    protected $fillable = [];

    /** Generated column: never written by the application. */
    protected $guarded = ['active_primary_key'];

    /** @return BelongsTo<Dai, $this> */
    public function dai(): BelongsTo
    {
        return $this->belongsTo(Dai::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assignment_type' => AssignmentType::class,
            'is_primary' => 'boolean',
            'assigned_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
