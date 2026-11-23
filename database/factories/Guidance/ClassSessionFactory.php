<?php

declare(strict_types=1);

namespace Database\Factories\Guidance;

use App\Domain\Guidance\Enums\SessionStatus;
use App\Domain\Guidance\Models\ClassSession;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ClassSession>
 */
final class ClassSessionFactory extends Factory
{
    protected $model = ClassSession::class;

    public function definition(): array
    {
        return [
            'class_id' => GuidanceClass::factory(),
            'teacher_user_id' => User::factory(),
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => SessionStatus::Scheduled,
        ];
    }
}
