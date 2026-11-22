<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Models;

use App\Domain\Dawah\Enums\AttendanceStatus;
use App\Domain\Dawah\Enums\ParticipantType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participant of a plan (08 §8.5); visible only through a plan of this office.
 *
 * @property int $id
 * @property int $activity_plan_id
 * @property ParticipantType $participant_type
 * @property int|null $participant_id
 * @property string|null $participant_name
 * @property string|null $phone_encrypted
 * @property AttendanceStatus $attendance_status
 * @property bool $transportation_required
 * @property string|null $notes
 */
final class ActivityParticipant extends Model
{
    protected $fillable = [];

    protected $hidden = ['phone_encrypted'];

    protected static function booted(): void
    {
        self::addGlobalScope('through_plan', fn (Builder $builder) => $builder->whereHas('plan'));
    }

    /** @return BelongsTo<DawahActivityPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(DawahActivityPlan::class, 'activity_plan_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'participant_type' => ParticipantType::class,
            'phone_encrypted' => 'encrypted',
            'attendance_status' => AttendanceStatus::class,
            'transportation_required' => 'boolean',
        ];
    }
}
