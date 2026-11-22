<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Models;

use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only timeline of a certificate, read through the certificate.
 *
 * @property int $id
 * @property int $certificate_id
 * @property int|null $actor_id
 * @property string $event_type
 * @property array<string, mixed> $metadata
 * @property CarbonImmutable $created_at
 */
final class CertificateEvent extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $fillable = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected static function booted(): void
    {
        self::addGlobalScope('through_certificate', fn (Builder $query) => $query->whereHas('certificate'));
    }

    /** @return BelongsTo<Certificate, $this> */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
