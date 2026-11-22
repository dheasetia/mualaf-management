<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use App\Domain\Converts\Models\Convert;
use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Scopes\EnrollmentScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Written only by the Guidance enrollment actions. Belongs to the office that
 * runs it (EnrollmentScope on service_tenant_id).
 *
 * @property int $id
 * @property string $uuid
 * @property int $convert_id
 * @property int $origin_tenant_id
 * @property int $service_tenant_id
 * @property int $program_id
 * @property int|null $class_id
 * @property CarbonImmutable $enrolled_at
 * @property CarbonImmutable $target_completion_date
 * @property EnrollmentStatus $status
 * @property CarbonImmutable|null $completed_at
 * @property string|null $withdrawn_reason
 * @property int|null $transferred_to_enrollment_id
 * @property string|null $attendance_percentage
 * @property string|null $final_score
 * @property int $enrolled_by
 * @property-read GuidanceProgram $program
 * @property-read GuidanceClass|null $guidanceClass
 * @property-read Convert $convert
 */
final class Enrollment extends Model
{
    use HasUuids;

    protected $fillable = [];

    /** Generated column: never written by the application. */
    protected $guarded = ['active_enrollment_key'];

    protected static function booted(): void
    {
        self::addGlobalScope(new EnrollmentScope);
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<Convert, $this> */
    public function convert(): BelongsTo
    {
        return $this->belongsTo(Convert::class);
    }

    /** @return BelongsTo<GuidanceProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(GuidanceProgram::class, 'program_id');
    }

    /** @return BelongsTo<GuidanceClass, $this> */
    public function guidanceClass(): BelongsTo
    {
        return $this->belongsTo(GuidanceClass::class, 'class_id');
    }

    /** @return HasMany<LearningProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(LearningProgress::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'immutable_datetime',
            'target_completion_date' => 'immutable_date',
            'status' => EnrollmentStatus::class,
            'completed_at' => 'immutable_datetime',
            'attendance_percentage' => 'decimal:2',
            'final_score' => 'decimal:2',
        ];
    }
}
