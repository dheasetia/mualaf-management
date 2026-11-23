<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\GeneratedStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Documents\GeneratedDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A letter generated from a template version and a frozen snapshot. Issued
 * letters are immutable (critical test #10): a correction is a new letter
 * and the cancellation of the old one.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $template_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $document_number
 * @property array<string, mixed> $snapshot_data
 * @property string|null $file_checksum
 * @property GeneratedStatus $status
 * @property int $generated_by
 * @property CarbonImmutable|null $issued_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property-read DocumentTemplate $template
 */
#[UseFactory(GeneratedDocumentFactory::class)]
final class GeneratedDocument extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<GeneratedDocumentFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    protected static function booted(): void
    {
        self::updating(function (self $document): void {
            if ($document->getOriginal('issued_at') !== null && $document->isDirty(['snapshot_data', 'template_id', 'document_number', 'subject_type', 'subject_id'])) {
                throw new LogicException('An issued document never changes; issue a new one and cancel this one.');
            }
        });
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function pdfPath(string $tenantUuid): string
    {
        return "tenants/{$tenantUuid}/generated/{$this->uuid}.pdf";
    }

    public function hasPdf(): bool
    {
        return $this->file_checksum !== null;
    }

    /** @return BelongsTo<DocumentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
            'status' => GeneratedStatus::class,
            'issued_at' => 'immutable_datetime',
        ];
    }
}
