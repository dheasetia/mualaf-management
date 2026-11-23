<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Enums\SubmissionStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Administration\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An administrative submission for a convert (10 §10.1). Status changes
 * only through SubmissionWorkflow.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $convert_id
 * @property int $submission_type_id
 * @property string $reference_no
 * @property SubmissionStatus $status
 * @property int|null $assigned_to
 * @property string|null $notes
 * @property string|null $correction_note
 * @property int|null $generated_document_id
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $escalated_at
 * @property int $created_by
 * @property-read SubmissionType $type
 */
#[UseFactory(SubmissionFactory::class)]
final class Submission extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<SubmissionType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(SubmissionType::class, 'submission_type_id');
    }

    /** @return HasMany<SubmissionDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(SubmissionDocument::class);
    }

    /** @return HasMany<SubmissionApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(SubmissionApproval::class)->orderBy('approval_stage');
    }

    /** @return HasMany<SubmissionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SubmissionEvent::class)->orderBy('created_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
        ];
    }
}
