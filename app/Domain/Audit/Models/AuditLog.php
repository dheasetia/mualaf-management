<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only by AuditLogger. Not tenant-scoped through BelongsToTenant because
 * platform events have no tenant; readers filter by tenant_id explicitly.
 *
 * @property int $id
 * @property CarbonImmutable $created_at
 * @property int|null $tenant_id
 * @property int|null $actor_id
 * @property ActorType $actor_type
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $old_values_redacted
 * @property array<string, mixed>|null $new_values_redacted
 * @property int|null $privileged_grant_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $request_id
 * @property-read User|null $actor
 * @property-read Tenant|null $tenant
 */
final class AuditLog extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $fillable = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return Attribute<string|null, string|null> */
    protected function ipAddress(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value): ?string => $value === null ? null : (inet_ntop($value) ?: null),
            set: static fn (?string $value): ?string => $value === null ? null : (inet_pton($value) ?: null),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'actor_type' => ActorType::class,
            'old_values_redacted' => 'array',
            'new_values_redacted' => 'array',
        ];
    }
}
