<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Models;

use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Certificates\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Written only by the Certificates actions. Once issued, the snapshot,
 * number and template never change (critical test #10 for documents).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $convert_id
 * @property int|null $enrollment_id
 * @property int|null $certificate_template_id
 * @property CertificateType $certificate_type
 * @property string|null $certificate_number
 * @property string|null $verification_token_hash
 * @property array<string, mixed>|null $snapshot_data
 * @property CarbonImmutable|null $issued_at
 * @property int|null $issued_by
 * @property int $requested_by
 * @property int|null $approved_by
 * @property string|null $file_checksum
 * @property CertificateStatus $status
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $revoked_by
 * @property string|null $revocation_reason
 * @property-read Tenant $tenant
 */
#[UseFactory(CertificateFactory::class)]
final class Certificate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    protected $guarded = ['active_certificate_key'];

    protected $hidden = ['verification_token_hash'];

    protected static function booted(): void
    {
        self::updating(function (self $certificate): void {
            if ($certificate->getOriginal('issued_at') !== null && $certificate->isDirty(['snapshot_data', 'certificate_number', 'certificate_template_id', 'convert_id', 'enrollment_id', 'issued_at'])) {
                throw new LogicException('An issued certificate keeps its snapshot, number and template.');
            }
        });
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** Storage path of the PDF (private disk), derived from the certificate. */
    public function pdfPath(string $tenantUuid): string
    {
        return "tenants/{$tenantUuid}/certificates/{$this->uuid}.pdf";
    }

    public function hasPdf(): bool
    {
        return $this->file_checksum !== null;
    }

    /** @return BelongsTo<CertificateTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'certificate_type' => CertificateType::class,
            'snapshot_data' => 'array',
            'issued_at' => 'immutable_datetime',
            'status' => CertificateStatus::class,
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
