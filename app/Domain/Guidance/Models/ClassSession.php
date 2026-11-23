<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use App\Domain\Guidance\Enums\SessionStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Guidance\ClassSessionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $class_id
 * @property int|null $lesson_id
 * @property int $teacher_user_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property SessionStatus $status
 * @property string|null $notes
 * @property-read GuidanceClass $guidanceClass
 */
#[UseFactory(ClassSessionFactory::class)]
final class ClassSession extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ClassSessionFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return BelongsTo<GuidanceClass, $this> */
    public function guidanceClass(): BelongsTo
    {
        return $this->belongsTo(GuidanceClass::class, 'class_id');
    }

    /** @return BelongsTo<GuidanceLesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(GuidanceLesson::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => SessionStatus::class,
        ];
    }
}
