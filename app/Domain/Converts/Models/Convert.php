<?php

declare(strict_types=1);

namespace App\Domain\Converts\Models;

use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Exceptions\ConvertTenantImmutable;
use App\Domain\Converts\Scopes\ConvertScope;
use App\Domain\Dai\Contracts\AssignableSubject;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Religion;
use App\Domain\Tenancy\Exceptions\TenantMismatch;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Text\NameSearchKey;
use Carbon\CarbonImmutable;
use Database\Factories\Converts\ConvertFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The national record of one person (BR-001). Created only through the
 * shahadah workflow (step 11). origin_tenant_id never changes through ordinary
 * saves; current_tenant_id moves only through the transfer service (step 13).
 *
 * @property int $id
 * @property string $uuid
 * @property int $origin_tenant_id
 * @property int $current_tenant_id
 * @property string $full_name
 * @property string|null $name_ar
 * @property string|null $islamic_name
 * @property string|null $previous_name
 * @property int|null $native_language_id
 * @property int|null $previous_religion_id
 * @property string|null $previous_religion_other
 * @property string $name_search_key
 * @property Gender $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property int $nationality_id
 * @property ConvertStatus $status
 * @property DisclosureLevel $disclosure_risk_level
 * @property int $primary_dai_id
 * @property int|null $prospect_id
 * @property int $created_by
 * @property-read Tenant $currentTenant
 * @property-read Country|null $nationality
 */
#[UseFactory(ConvertFactory::class)]
final class Convert extends Model implements AssignableSubject
{
    /** @use HasFactory<ConvertFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'disclosure_risk_level' => 'normal',
    ];

    protected static function booted(): void
    {
        self::addGlobalScope(new ConvertScope);

        self::creating(function (self $convert): void {
            $contextId = app(TenantContext::class)->requireId();

            foreach (['origin_tenant_id', 'current_tenant_id'] as $column) {
                $given = $convert->getAttribute($column);

                if ($given !== null && (int) $given !== $contextId) {
                    throw TenantMismatch::onCreate(self::class, (int) $given, $contextId);
                }

                $convert->setAttribute($column, $contextId);
            }
        });

        self::updating(function (self $convert): void {
            foreach (['origin_tenant_id', 'current_tenant_id'] as $column) {
                if ($convert->isDirty($column)) {
                    throw ConvertTenantImmutable::make($column);
                }
            }
        });

        self::saving(function (self $convert): void {
            if ($convert->isDirty('full_name')) {
                $convert->name_search_key = NameSearchKey::make($convert->full_name);
            }
        });
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function responsibleTenantId(): int
    {
        return $this->current_tenant_id;
    }

    public function setPrimaryDai(int $daiId): void
    {
        $this->primary_dai_id = $daiId;
    }

    /** @return BelongsTo<Tenant, $this> */
    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    /** @return HasMany<ConvertContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ConvertContact::class);
    }

    /** @return HasMany<ConvertStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ConvertStatusHistory::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_id');
    }

    /** @return BelongsTo<Language, $this> */
    public function nativeLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'native_language_id');
    }

    /** @return BelongsTo<Religion, $this> */
    public function previousReligion(): BelongsTo
    {
        return $this->belongsTo(Religion::class, 'previous_religion_id');
    }

    /** Shown instead of the name where the convert must not be identifiable. */
    public function alias(): string
    {
        return __('converts.alias', ['code' => strtoupper(substr(str_replace('-', '', $this->uuid), 0, 6))]);
    }

    /** @return HasMany<ConvertIdentifier, $this> */
    public function identifiers(): HasMany
    {
        return $this->hasMany(ConvertIdentifier::class);
    }

    /** "A. R." for "Abdullah Reyes": what other offices may see by default (03 §3.4). */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->full_name)) ?: [];

        return implode(' ', array_map(static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)).'.', array_filter($words)));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'immutable_date',
            'status' => ConvertStatus::class,
            'disclosure_risk_level' => DisclosureLevel::class,
        ];
    }
}
