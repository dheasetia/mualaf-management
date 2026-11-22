<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Models;

use App\Domain\Dai\Contracts\AssignableSubject;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Religion;
use App\Domain\Prospects\Enums\InterestLevel;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Support\Identity\PhoneNumber;
use App\Support\Tenancy\BelongsToTenant;
use App\Support\Text\NameSearchKey;
use Carbon\CarbonImmutable;
use Database\Factories\Prospects\ProspectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Written only by the Prospects actions and DaiAssignmentService; nothing is
 * mass assignable. name_search_key and phone_fingerprint follow the name and
 * phone on every save.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $full_name
 * @property string $name_search_key
 * @property Gender $gender
 * @property int|null $nationality_id
 * @property int|null $native_language_id
 * @property int|null $current_religion_id
 * @property string|null $current_religion_other
 * @property string|null $phone_encrypted
 * @property string|null $phone_fingerprint
 * @property string|null $email_encrypted
 * @property string|null $occupation
 * @property int|null $residence_city_id
 * @property string|null $residence_description
 * @property int $primary_dai_id
 * @property ProspectSource $source_type
 * @property string|null $source_description
 * @property InterestLevel|null $interest_level
 * @property ProspectStatus $status
 * @property CarbonImmutable|null $first_contact_at
 * @property CarbonImmutable|null $last_contact_at
 * @property CarbonImmutable|null $next_follow_up_at
 * @property int $created_by
 * @property CarbonImmutable|null $converted_at
 * @property int|null $convert_id
 */
#[UseFactory(ProspectFactory::class)]
final class Prospect extends Model implements AssignableSubject
{
    use BelongsToTenant;

    /** @use HasFactory<ProspectFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    protected $hidden = ['phone_encrypted', 'phone_fingerprint', 'email_encrypted'];

    private static bool $officeMoveAllowed = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'new',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $prospect): void {
            if ($prospect->isDirty('full_name')) {
                $prospect->name_search_key = NameSearchKey::make($prospect->full_name);
            }

            if ($prospect->isDirty('phone_encrypted')) {
                $prospect->phone_fingerprint = PhoneNumber::fingerprint($prospect->phone_encrypted);
            }
        });
    }

    /**
     * Moves the prospect to another office (prospect transfer only).
     */
    public function moveToOffice(int $tenantId): void
    {
        self::$officeMoveAllowed = true;

        try {
            $this->tenant_id = $tenantId;
            $this->save();
        } finally {
            self::$officeMoveAllowed = false;
        }
    }

    public function tenantMoveAllowed(): bool
    {
        return self::$officeMoveAllowed;
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function responsibleTenantId(): int
    {
        return $this->tenant_id;
    }

    public function setPrimaryDai(int $daiId): void
    {
        $this->primary_dai_id = $daiId;
    }

    /** @return HasMany<ProspectStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProspectStatusHistory::class);
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
    public function currentReligion(): BelongsTo
    {
        return $this->belongsTo(Religion::class, 'current_religion_id');
    }

    /** @return BelongsTo<City, $this> */
    public function residenceCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'residence_city_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'phone_encrypted' => 'encrypted',
            'email_encrypted' => 'encrypted',
            'source_type' => ProspectSource::class,
            'interest_level' => InterestLevel::class,
            'status' => ProspectStatus::class,
            'first_contact_at' => 'immutable_datetime',
            'last_contact_at' => 'immutable_datetime',
            'next_follow_up_at' => 'immutable_datetime',
        ];
    }
}
