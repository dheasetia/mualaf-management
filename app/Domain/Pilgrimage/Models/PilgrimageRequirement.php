<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $program_id
 * @property string $code
 * @property string $description
 * @property bool $required
 */
final class PilgrimageRequirement extends Model
{
    protected $fillable = [];

    protected $table = 'pilgrimage_requirements';

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
            'required' => 'boolean',
        ];
    }
}
