<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\UserTenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $tenant_id
 * @property MembershipStatus $status
 * @property CarbonImmutable $joined_at
 * @property CarbonImmutable|null $left_at
 */
#[UseFactory(UserTenantMembershipFactory::class)]
final class UserTenantMembership extends Model
{
    /** @use HasFactory<UserTenantMembershipFactory> */
    use HasFactory;

    /** Status, user and tenant are set by the membership actions only. */
    protected $fillable = [];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
        ];
    }
}
