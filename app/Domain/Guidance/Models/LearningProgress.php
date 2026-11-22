<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use App\Domain\Guidance\Enums\ProgressSource;
use App\Domain\Guidance\Enums\ProgressStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Guidance\LearningProgressFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $enrollment_id
 * @property int $lesson_id
 * @property ProgressStatus $progress_status
 * @property string|null $score
 * @property string|null $competency_notes
 * @property bool $needs_mentoring
 * @property int|null $assessed_by
 * @property CarbonImmutable|null $assessed_at
 * @property ProgressSource $source
 * @property string|null $notes
 */
#[UseFactory(LearningProgressFactory::class)]
final class LearningProgress extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LearningProgressFactory> */
    use HasFactory;

    protected $fillable = [];

    protected $table = 'learning_progress';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'progress_status' => ProgressStatus::class,
            'score' => 'decimal:2',
            'needs_mentoring' => 'boolean',
            'assessed_at' => 'immutable_datetime',
            'source' => ProgressSource::class,
        ];
    }
}
