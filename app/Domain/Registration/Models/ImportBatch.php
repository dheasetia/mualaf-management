<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Registration\Enums\ImportBatchStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Registration\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An office's upload of historical converts (11 §11.5). Written only by
 * HistoricalImports and its jobs.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $import_type
 * @property string $original_filename
 * @property ImportBatchStatus $status
 * @property int $total_rows
 * @property int $success_rows
 * @property int $failed_rows
 * @property int $duplicate_rows
 * @property int $uploaded_by
 * @property int|null $started_by
 * @property string|null $error_summary
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property-read User $uploader
 */
#[UseFactory(ImportBatchFactory::class)]
final class ImportBatch extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    use HasUuids;

    public const string TYPE_HISTORICAL_CONVERTS = 'historical_converts';

    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return HasMany<ImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'total_rows' => 'integer',
            'success_rows' => 'integer',
            'failed_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
