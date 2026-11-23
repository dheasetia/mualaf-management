<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Enums\AssignmentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $submission_id
 * @property int $user_id
 * @property AssignmentType $assignment_type
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $completed_at
 */
final class SubmissionAssignment extends Model
{
    protected $fillable = [];

    protected static function booted(): void
    {
        self::addGlobalScope('through_submission', fn (Builder $builder) => $builder->whereHas('submission'));
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assignment_type' => AssignmentType::class,
            'assigned_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
