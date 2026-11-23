<?php

declare(strict_types=1);

namespace App\Domain\Privileged\Models;

use App\Domain\Privileged\Enums\PrivilegedScope;
use App\Domain\Privileged\Enums\PrivilegedStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Privileged\PrivilegedAccessRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A break-glass request and, once approved, the grant (04 §4.8). A platform
 * record: tenant_id names the office of the convert concerned.
 *
 * @property int $id
 * @property string $uuid
 * @property int $requested_by
 * @property int|null $tenant_id
 * @property string $resource_type
 * @property int|null $resource_id
 * @property string $reason
 * @property list<string> $requested_scope
 * @property int $requested_duration_minutes
 * @property PrivilegedStatus $status
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $decision_note
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $revoked_by
 */
#[UseFactory(PrivilegedAccessRequestFactory::class)]
final class PrivilegedAccessRequest extends Model
{
    /** @use HasFactory<PrivilegedAccessRequestFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function isActive(): bool
    {
        return $this->status === PrivilegedStatus::Active && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function allows(PrivilegedScope $scope): bool
    {
        return in_array($scope->value, $this->requested_scope, true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_scope' => 'array',
            'requested_duration_minutes' => 'integer',
            'status' => PrivilegedStatus::class,
            'approved_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
