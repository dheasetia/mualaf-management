<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only timeline of a submission.
 *
 * @property int $id
 * @property int $submission_id
 * @property int|null $actor_id
 * @property string $event_type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $reason
 * @property array<string, mixed> $metadata
 * @property CarbonImmutable $created_at
 */
final class SubmissionEvent extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.u';

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
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
