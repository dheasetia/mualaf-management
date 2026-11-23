<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Models;

use App\Domain\Converts\Models\Convert;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Scopes\TransferPartyScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Written only by the Transfers actions.
 *
 * @property int $id
 * @property string $uuid
 * @property int $convert_id
 * @property int $from_tenant_id
 * @property int $to_tenant_id
 * @property int $requested_by
 * @property string $reason
 * @property CarbonImmutable $requested_at
 * @property TransferStatus $status
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property int|null $accepted_by
 * @property CarbonImmutable|null $accepted_at
 * @property int|null $new_primary_dai_id
 * @property CarbonImmutable|null $escalated_at
 * @property string|null $escalation_note
 * @property CarbonImmutable|null $completed_at
 * @property-read Tenant $fromTenant
 * @property-read Tenant $toTenant
 * @property-read User $requester
 */
final class ConvertTransferRequest extends Model
{
    use HasUuids;

    protected $fillable = [];

    /** Generated column: never written by the application. */
    protected $guarded = ['active_convert_key'];

    protected static function booted(): void
    {
        self::addGlobalScope(new TransferPartyScope);
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The convert is in the current office of from_tenant_id until completion;
     * read it through TenantContext::bypass from the destination office.
     *
     * @return BelongsTo<Convert, $this>
     */
    public function convert(): BelongsTo
    {
        return $this->belongsTo(Convert::class);
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

    /** @return HasMany<ConvertTransferEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ConvertTransferEvent::class, 'transfer_request_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'status' => TransferStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
