<?php

declare(strict_types=1);

namespace App\Domain\Dai\Models;

use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Enums\EmploymentStatus;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Dai\DaiFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Written only by CreateDai, UpdateDai and ChangeDaiStatus; nothing is mass assignable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $employee_number
 * @property string $full_name
 * @property string|null $full_name_ar
 * @property Gender $gender
 * @property int|null $nationality_id
 * @property string|null $phone_encrypted
 * @property string|null $email
 * @property string|null $education
 * @property list<string> $specialties
 * @property int|null $supervisor_dai_id
 * @property CarbonImmutable|null $joined_at
 * @property EmploymentStatus $employment_status
 * @property DaiStatus $status
 * @property-read Dai|null $supervisor
 * @property-read User|null $user
 */
#[UseFactory(DaiFactory::class)]
final class Dai extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DaiFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    /** Never shown or logged in clear (AuditRedactor masks hidden attributes). */
    protected $hidden = ['phone_encrypted'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'specialties' => '[]',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_id');
    }

    /** @return BelongsTo<self, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_dai_id');
    }

    /** @return HasMany<self, $this> */
    public function supervisees(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_dai_id');
    }

    /** @return BelongsToMany<Language, $this, DaiLanguage> */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'dai_languages')
            ->using(DaiLanguage::class)
            ->withPivot('proficiency');
    }

    /** @return HasMany<DaiAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(DaiAssignment::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', DaiStatus::Active);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'phone_encrypted' => 'encrypted',
            'specialties' => 'array',
            'joined_at' => 'immutable_date',
            'employment_status' => EmploymentStatus::class,
            'status' => DaiStatus::class,
        ];
    }
}
