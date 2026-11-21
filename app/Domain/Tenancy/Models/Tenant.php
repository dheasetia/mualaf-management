<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Region;
use App\Domain\Tenancy\Enums\TenantStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An Islamic Center. Status changes go through the Tenancy actions, never mass assignment.
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name_ar
 * @property string $name_en
 * @property TenantStatus $status
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $suspended_at
 */
#[UseFactory(TenantFactory::class)]
final class Tenant extends Model implements HasName
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'code', 'name_ar', 'name_en', 'region_id', 'city_id', 'address', 'phone', 'email',
        'latitude', 'longitude', 'logo_path', 'director_name',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getFilamentName(): string
    {
        return $this->name_ar;
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasOne<TenantSetting, $this> */
    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    /** @return HasMany<UserTenantMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(UserTenantMembership::class);
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'activated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
