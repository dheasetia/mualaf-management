<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Registration\Enums\ImportRowStatus;
use App\Support\Tenancy\BelongsToTenant;
use Database\Factories\Registration\ImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One spreadsheet row of an import batch; the cells are kept encrypted.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $import_batch_id
 * @property int $row_number
 * @property array<string, string> $source_data_encrypted
 * @property ImportRowStatus $status
 * @property string|null $error_message
 * @property string|null $resulting_record_type
 * @property int|null $resulting_record_id
 * @property-read ImportBatch $batch
 */
#[UseFactory(ImportRowFactory::class)]
final class ImportRow extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ImportRowFactory> */
    use HasFactory;

    protected $fillable = [];

    protected $hidden = ['source_data_encrypted'];

    /** @return BelongsTo<ImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function cell(string $column): ?string
    {
        $value = trim((string) ($this->source_data_encrypted[$column] ?? ''));

        return $value === '' ? null : $value;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_data_encrypted' => 'encrypted:array',
            'status' => ImportRowStatus::class,
            'row_number' => 'integer',
        ];
    }
}
