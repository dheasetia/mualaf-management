<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Models;

use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Pilgrimage\PilgrimageRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $program_id
 * @property int $convert_id
 * @property RegistrationStatus $status
 * @property int|null $eligibility_checked_by
 * @property CarbonImmutable|null $eligibility_checked_at
 * @property string|null $eligibility_notes
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $withdrawal_reason
 * @property int $registered_by
 * @property-read PilgrimageProgram $program
 */
#[UseFactory(PilgrimageRegistrationFactory::class)]
final class PilgrimageRegistration extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PilgrimageRegistrationFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return BelongsTo<PilgrimageProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PilgrimageProgram::class, 'program_id');
    }

    /** @return HasMany<PilgrimageRegistrationRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(PilgrimageRegistrationRequirement::class, 'registration_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'eligibility_checked_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
