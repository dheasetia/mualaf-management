<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ExportType;
use App\Domain\Reporting\Scopes\ExportOwnerScope;
use Carbon\CarbonImmutable;
use Database\Factories\Reporting\ExportJobFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An export request (11 §11.5): an encrypted file kept for 24 hours.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property int $requested_by
 * @property ExportType $export_type
 * @property array<string, mixed> $filters
 * @property string|null $reason
 * @property int|null $row_count
 * @property ExportStatus $status
 * @property string|null $storage_path
 * @property string|null $error_summary
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $downloaded_at
 * @property int $download_count
 */
#[UseFactory(ExportJobFactory::class)]
final class ExportJob extends Model
{
    /** @use HasFactory<ExportJobFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    protected static function booted(): void
    {
        self::addGlobalScope(new ExportOwnerScope);
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Ready && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'export_type' => ExportType::class,
            'filters' => 'array',
            'status' => ExportStatus::class,
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'downloaded_at' => 'immutable_datetime',
            'row_count' => 'integer',
            'download_count' => 'integer',
        ];
    }
}
