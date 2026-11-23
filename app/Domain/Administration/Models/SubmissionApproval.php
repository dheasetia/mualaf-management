<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Enums\ApprovalDecision;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $submission_id
 * @property int $approval_stage
 * @property int $approver_id
 * @property ApprovalDecision $decision
 * @property string|null $reason
 * @property CarbonImmutable $decided_at
 */
final class SubmissionApproval extends Model
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
            'approval_stage' => 'integer',
            'decision' => ApprovalDecision::class,
            'decided_at' => 'immutable_datetime',
        ];
    }
}
