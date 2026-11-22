<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Models;

use App\Domain\Transfers\Enums\TransferStatus;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only timeline of a transfer request, read through the request.
 *
 * @property int $id
 * @property int $transfer_request_id
 * @property int|null $actor_id
 * @property int|null $tenant_id
 * @property string $event_type
 * @property TransferStatus|null $from_status
 * @property TransferStatus|null $to_status
 * @property array<string, mixed> $metadata
 * @property CarbonImmutable $created_at
 */
final class ConvertTransferEvent extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $fillable = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected static function booted(): void
    {
        self::addGlobalScope('through_request', fn (Builder $query) => $query->whereHas('request'));
    }

    /** @return BelongsTo<ConvertTransferRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ConvertTransferRequest::class, 'transfer_request_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => TransferStatus::class,
            'to_status' => TransferStatus::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
