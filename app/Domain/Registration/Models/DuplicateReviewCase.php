<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Converts\Models\Convert;
use App\Domain\Registration\Enums\CandidateType;
use App\Domain\Registration\Enums\MatchReason;
use App\Domain\Registration\Enums\ReviewDecision;
use App\Domain\Registration\Enums\ReviewStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Registration\DuplicateReviewCaseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Owned by the office whose registration triggered it; reviewed there by
 * duplicates.review. The possible convert may belong to another office and
 * is shown only in the limited form of the national search.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property CandidateType $candidate_type
 * @property int|null $candidate_id
 * @property string $candidate_key
 * @property int $possible_convert_id
 * @property MatchReason $match_reason
 * @property string|null $match_score
 * @property array<string, mixed> $match_details
 * @property ReviewStatus $status
 * @property ReviewDecision|null $decision
 * @property int|null $reviewed_by
 * @property string|null $notes
 * @property CarbonImmutable|null $resolved_at
 * @property int $created_by
 * @property-read User $creator
 */
#[UseFactory(DuplicateReviewCaseFactory::class)]
final class DuplicateReviewCase extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DuplicateReviewCaseFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Read across offices only through TenantContext::bypass (DuplicateReview).
     *
     * @return BelongsTo<Convert, $this>
     */
    public function possibleConvert(): BelongsTo
    {
        return $this->belongsTo(Convert::class, 'possible_convert_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'candidate_type' => CandidateType::class,
            'match_reason' => MatchReason::class,
            'match_score' => 'decimal:3',
            'match_details' => 'array',
            'status' => ReviewStatus::class,
            'decision' => ReviewDecision::class,
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
