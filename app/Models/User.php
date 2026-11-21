<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\UserTenantMembership;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'center' => $this->accessibleTenants()->exists(),
            // Central access comes from platform roles, added with RBAC (implementation step 4).
            default => false,
        };
    }

    /** @return Collection<int, Tenant> */
    public function getTenants(Panel $panel): Collection
    {
        return $this->accessibleTenants()->orderBy('name_ar')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Tenant
            && $this->accessibleTenants()->whereKey($tenant->getKey())->exists();
    }

    /**
     * Active tenants where this user has an active membership.
     *
     * @return Builder<Tenant>
     */
    public function accessibleTenants(): Builder
    {
        return Tenant::query()
            ->where('status', TenantStatus::Active)
            ->whereHas('memberships', fn (Builder $query) => $query
                ->where('user_id', $this->id)
                ->where('status', MembershipStatus::Active));
    }

    /** @return HasMany<UserTenantMembership, $this> */
    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(UserTenantMembership::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
