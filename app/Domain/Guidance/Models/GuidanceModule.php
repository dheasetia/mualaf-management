<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $program_id
 * @property string $title
 * @property int $sort_order
 * @property string $weight
 * @property bool $required
 * @property-read GuidanceProgram $program
 */
final class GuidanceModule extends Model
{
    protected $fillable = [];

    /** @return BelongsTo<GuidanceProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(GuidanceProgram::class, 'program_id');
    }

    /** @return HasMany<GuidanceLesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(GuidanceLesson::class, 'module_id')->orderBy('sort_order');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'required' => 'boolean',
        ];
    }
}
