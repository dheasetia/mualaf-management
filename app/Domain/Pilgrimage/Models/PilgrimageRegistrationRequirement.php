<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Models;

use App\Domain\Pilgrimage\Enums\RequirementStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $registration_id
 * @property int $requirement_id
 * @property int|null $document_id
 * @property RequirementStatus $status
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $note
 * @property-read PilgrimageRequirement $requirement
 */
final class PilgrimageRegistrationRequirement extends Model
{
    /** @return BelongsTo<PilgrimageRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(PilgrimageRequirement::class, 'requirement_id');
    }

    protected $fillable = [];

    protected $table = 'pilgrimage_registration_requirements';

    protected static function booted(): void
    {
        self::addGlobalScope('through_parent', fn (Builder $builder) => $builder->whereHas('registration'));
    }

    /** @return BelongsTo<PilgrimageRegistration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(PilgrimageRegistration::class, 'registration_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RequirementStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
