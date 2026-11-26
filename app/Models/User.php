<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Identity\Models\UserRoleAssignment;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $phone_encrypted phone as +digits
 * @property CarbonImmutable|null $whatsapp_opt_in_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'phone_encrypted', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
        'locale' => 'ar',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'center' => $this->accessibleTenants()->exists(),
            'central' => app(Authorizer::class)->hasAnyPlatformRole($this),
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

    /**
     * Permission check in the current scope: office roles of the tenant in context,
     * or platform roles when there is no tenant context.
     */
    public function hasPermission(string $permission): bool
    {
        return app(Authorizer::class)->check($this, $permission);
    }

    /**
     * Notifications of the office in context (or platform ones in Central),
     * so staff of several offices only see each office's alerts there.
     *
     * @return MorphMany<DatabaseNotification, $this>
     */
    public function notifications(): MorphMany
    {
        $tenantId = app(TenantContext::class)->id();

        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest()
            ->where(fn ($query) => $tenantId === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', $tenantId));
    }

    /** @return HasMany<UserRoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
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
            'is_active' => 'boolean',
            'phone_encrypted' => 'encrypted',
            'last_login_at' => 'immutable_datetime',
            'whatsapp_opt_in_at' => 'immutable_datetime',
        ];
    }

    /** WhatsApp alerts go only to users who opted in with their own number (open question #9). */
    public function receivesWhatsApp(): bool
    {
        return $this->whatsapp_opt_in_at !== null && filled($this->phone_encrypted);
    }
}
