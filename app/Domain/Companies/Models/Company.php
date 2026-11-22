<?php

declare(strict_types=1);

namespace App\Domain\Companies\Models;

use App\Domain\Companies\Scopes\CompanyScope;
use App\Domain\Platform\Models\City;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Database\Factories\Companies\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An employer, in the national directory (tenant_id NULL, Central) or owned
 * by one office. Written by SaveCompany and PromoteCompany only. The owner
 * is set on creation from the tenant in context and never changes, except
 * when Central promotes the company to the national directory.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property string $name_ar
 * @property string|null $name_en
 * @property string|null $registration_number
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property int|null $city_id
 * @property string|null $address
 * @property string|null $district
 * @property string|null $postal_code
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $google_maps_url
 * @property string|null $notes
 * @property int $created_by
 * @property-read Tenant|null $tenant
 * @property-read City|null $city
 */
#[UseFactory(CompanyFactory::class)]
final class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    private static bool $promoting = false;

    protected static function booted(): void
    {
        self::addGlobalScope(new CompanyScope);

        self::creating(function (self $company): void {
            $company->tenant_id = app(TenantContext::class)->id();
        });

        self::updating(function (self $company): void {
            if ($company->isDirty('tenant_id') && ! self::$promoting) {
                throw new LogicException('companies.tenant_id changes only through PromoteCompany.');
            }
        });
    }

    /** Moves an office company to the national directory (PromoteCompany only). */
    public function makeNational(): void
    {
        self::$promoting = true;

        try {
            $this->tenant_id = null;
            $this->save();
        } finally {
            self::$promoting = false;
        }
    }

    public function isNational(): bool
    {
        return $this->tenant_id === null;
    }

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

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
