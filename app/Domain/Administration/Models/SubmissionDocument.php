<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Enums\DocumentCheck;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to a submission for one requirement, checked by the
 * verifier. The file is owned by the submission, not the convert.
 *
 * @property int $id
 * @property int $submission_id
 * @property int $document_id
 * @property string $requirement_code
 * @property DocumentCheck $verification_status
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $rejection_note
 */
final class SubmissionDocument extends Model
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
            'verification_status' => DocumentCheck::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
