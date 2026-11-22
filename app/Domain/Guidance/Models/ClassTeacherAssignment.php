<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use App\Domain\Guidance\Enums\TeacherRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $class_id
 * @property int $user_id
 * @property TeacherRole $role
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $released_at
 * @property-read User $user
 */
final class ClassTeacherAssignment extends Model
{
    protected $fillable = [];

    /** Generated column: never written by the application. */
    protected $guarded = ['active_teacher_key'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => TeacherRole::class,
            'assigned_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }
}
