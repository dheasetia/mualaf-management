<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use App\Domain\Guidance\Enums\AttendanceStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Guidance\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $class_session_id
 * @property int $enrollment_id
 * @property AttendanceStatus $status
 * @property int $recorded_by
 * @property CarbonImmutable $recorded_at
 * @property string|null $notes
 */
#[UseFactory(AttendanceFactory::class)]
final class Attendance extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
