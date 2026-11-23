<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Models;

use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Enums\ProgramType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Pilgrimage\PilgrimageProgramFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An umrah or hajj program of the office (10 §10.2).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property ProgramType $program_type
 * @property string $name
 * @property int $season_year
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable $registration_deadline
 * @property int $capacity
 * @property array{min_months_since_shahadah?: int|null, require_completed_guidance?: bool} $eligibility_rules
 * @property ProgramStatus $status
 * @property int $created_by
 */
#[UseFactory(PilgrimageProgramFactory::class)]
final class PilgrimageProgram extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PilgrimageProgramFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    protected $table = 'pilgrimage_programs';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return HasMany<PilgrimageRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(PilgrimageRequirement::class, 'program_id');
    }

    /** @return HasMany<PilgrimageRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(PilgrimageRegistration::class, 'program_id');
    }

    /** @return HasMany<PilgrimageGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(PilgrimageGroup::class, 'program_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'program_type' => ProgramType::class,
            'season_year' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'registration_deadline' => 'immutable_date',
            'capacity' => 'integer',
            'eligibility_rules' => 'array',
            'status' => ProgramStatus::class,
        ];
    }
}
