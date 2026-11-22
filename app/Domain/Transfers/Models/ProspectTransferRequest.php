<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Models;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Enums\ProspectTransferStatus;
use App\Domain\Transfers\Scopes\TransferPartyScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $prospect_id
 * @property int $from_tenant_id
 * @property int $to_tenant_id
 * @property int $requested_by
 * @property string $reason
 * @property ProspectTransferStatus $status
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property int|null $new_primary_dai_id
 * @property CarbonImmutable|null $completed_at
 * @property-read Tenant $fromTenant
 * @property-read Tenant $toTenant
 * @property-read User $requester
 */
final class ProspectTransferRequest extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected $guarded = ['active_prospect_key'];

    protected static function booted(): void
    {
        self::addGlobalScope(new TransferPartyScope);
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function fromTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'from_tenant_id');
    }

    /** @return BelongsTo<Tenant, $this> */
    public function toTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'to_tenant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProspectTransferStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
