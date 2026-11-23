<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use App\Domain\Guidance\Enums\ClassStatus;
use App\Domain\Guidance\Enums\GenderPolicy;
use App\Domain\Platform\Models\Language;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Guidance\GuidanceClassFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $program_id
 * @property string $code
 * @property string $name
 * @property int $language_id
 * @property GenderPolicy $gender_policy
 * @property int $capacity
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property ClassStatus $status
 * @property-read GuidanceProgram $program
 */
#[UseFactory(GuidanceClassFactory::class)]
final class GuidanceClass extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<GuidanceClassFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    protected $table = 'guidance_classes';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<GuidanceProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(GuidanceProgram::class, 'program_id');
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return HasMany<ClassSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'class_id');
    }

    /** @return HasMany<ClassTeacherAssignment, $this> */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class, 'class_id');
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    /** @return HasMany<ClassSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'class_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender_policy' => GenderPolicy::class,
            'capacity' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => ClassStatus::class,
        ];
    }
}
