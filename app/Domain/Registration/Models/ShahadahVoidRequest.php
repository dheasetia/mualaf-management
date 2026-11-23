<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Registration\Enums\VoidRequestStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Head of Dawah's request to void a registration and its shahadah (07
 * §7.1), decided in Central. Written only by ShahadahVoiding. Not scoped to
 * a tenant: the office reads its own rows, Central reads all.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $convert_registration_id
 * @property int $requested_by
 * @property string $reason
 * @property VoidRequestStatus $status
 * @property int|null $decided_by
 * @property string|null $decision_note
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable $created_at
 * @property-read Tenant $tenant
 * @property-read User $requestedBy
 */
final class ShahadahVoidRequest extends Model
{
    use HasUuids;

    protected $fillable = [];

    /** Generated column: never written by the application. */
    protected $guarded = ['pending_key'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => VoidRequestStatus::class,
            'decided_at' => 'immutable_datetime',
        ];
    }
}
