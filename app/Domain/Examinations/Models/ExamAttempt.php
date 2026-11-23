<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Models;

use App\Domain\Examinations\Enums\AttemptStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Examinations\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $exam_id
 * @property int $enrollment_id
 * @property int|null $examiner_user_id
 * @property int $attempt_number
 * @property CarbonImmutable|null $attempted_at
 * @property AttemptStatus $status
 * @property-read Exam $exam
 * @property-read ExamResult|null $result
 */
#[UseFactory(ExamAttemptFactory::class)]
final class ExamAttempt extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ExamAttemptFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return HasMany<ExamScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(ExamScore::class);
    }

    /** @return HasOne<ExamResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(ExamResult::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'attempted_at' => 'immutable_datetime',
            'status' => AttemptStatus::class,
        ];
    }
}
