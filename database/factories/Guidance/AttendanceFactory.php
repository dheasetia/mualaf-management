<?php

declare(strict_types=1);

namespace Database\Factories\Guidance;

use App\Domain\Converts\Models\Convert;
use App\Domain\Guidance\Enums\AttendanceStatus;
use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Models\Attendance;
use App\Domain\Guidance\Models\ClassSession;
use App\Domain\Guidance\Models\Enrollment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<Attendance>
 */
final class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'enrollment_id' => fn (array $attributes): int => self::enrollmentFor((int) $attributes['class_session_id']),
            'status' => AttendanceStatus::Present,
            'recorded_by' => User::factory(),
            'recorded_at' => now(),
        ];
    }

    /** An enrollment in the session's class, for a convert of the tenant in context. */
    public static function enrollmentFor(int $sessionId): int
    {
        $session = ClassSession::query()->with('guidanceClass')->findOrFail($sessionId);
        $convert = Convert::factory()->create();
        $tenantId = app(TenantContext::class)->requireId();

        $enrollment = new Enrollment;
        $enrollment->convert_id = $convert->id;
        $enrollment->origin_tenant_id = $tenantId;
        $enrollment->service_tenant_id = $tenantId;
        $enrollment->program_id = $session->guidanceClass->program_id;
        $enrollment->class_id = $session->class_id;
        $enrollment->enrolled_at = now()->toImmutable();
        $enrollment->target_completion_date = now()->addMonths(3)->toImmutable();
        $enrollment->status = EnrollmentStatus::Active;
        $enrollment->enrolled_by = $convert->created_by;
        $enrollment->save();

        return $enrollment->id;
    }
}
