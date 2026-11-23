<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $program_id
 * @property string $group_name
 * @property int $leader_user_id
 * @property int $capacity
 */
final class PilgrimageGroup extends Model
{
    /** @return BelongsToMany<PilgrimageRegistration, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(PilgrimageRegistration::class, 'pilgrimage_group_members', 'group_id', 'pilgrimage_registration_id')->withTimestamps();
    }

    protected $fillable = [];

    protected $table = 'pilgrimage_groups';

    protected static function booted(): void
    {
        self::addGlobalScope('through_parent', fn (Builder $builder) => $builder->whereHas('program'));
    }

    /** @return BelongsTo<PilgrimageProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PilgrimageProgram::class, 'program_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }
}
