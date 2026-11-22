<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Models;

use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Enums\ExamType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Examinations\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $program_id
 * @property int|null $class_id
 * @property string $title
 * @property ExamType $exam_type
 * @property string $maximum_score
 * @property string $passing_score
 * @property CarbonImmutable|null $scheduled_at
 * @property ExamStatus $status
 */
#[UseFactory(ExamFactory::class)]
final class Exam extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return HasMany<ExamComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(ExamComponent::class);
    }

    /** @return HasMany<ExamAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'exam_type' => ExamType::class,
            'maximum_score' => 'decimal:2',
            'passing_score' => 'decimal:2',
            'scheduled_at' => 'immutable_datetime',
            'status' => ExamStatus::class,
        ];
    }
}
