<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use App\Domain\Guidance\Scopes\ProgramScope;
use Database\Factories\Guidance\GuidanceProgramFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * National template (tenant_id NULL) or an office's own program.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $code
 * @property string $name_ar
 * @property string|null $name_en
 * @property int $duration_days
 * @property string $minimum_attendance_percentage
 * @property string $passing_score
 * @property int|null $based_on_program_id
 * @property bool $active
 */
#[UseFactory(GuidanceProgramFactory::class)]
final class GuidanceProgram extends Model
{
    /** @use HasFactory<GuidanceProgramFactory> */
    use HasFactory;

    protected $fillable = [];

    /** Generated column: never written by the application. */
    protected $guarded = ['national_code_key'];

    protected static function booted(): void
    {
        self::addGlobalScope(new ProgramScope);
    }

    public function isNational(): bool
    {
        return $this->tenant_id === null;
    }

    /** @return HasMany<GuidanceModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(GuidanceModule::class, 'program_id')->orderBy('sort_order');
    }

    /** @return HasManyThrough<GuidanceLesson, GuidanceModule, $this> */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(GuidanceLesson::class, GuidanceModule::class, 'program_id', 'module_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'minimum_attendance_percentage' => 'decimal:2',
            'passing_score' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
