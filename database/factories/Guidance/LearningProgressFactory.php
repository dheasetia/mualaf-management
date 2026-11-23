<?php

declare(strict_types=1);

namespace Database\Factories\Guidance;

use App\Domain\Guidance\Enums\ProgressStatus;
use App\Domain\Guidance\Models\ClassSession;
use App\Domain\Guidance\Models\GuidanceLesson;
use App\Domain\Guidance\Models\GuidanceModule;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Guidance\Models\LearningProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<LearningProgress>
 */
final class LearningProgressFactory extends Factory
{
    protected $model = LearningProgress::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => fn (): int => AttendanceFactory::enrollmentFor(ClassSession::factory()->create()->id),
            'lesson_id' => fn (): int => GuidanceLesson::query()->create(['module_id' => GuidanceModule::query()->create(['program_id' => GuidanceProgram::factory()->create()->id, 'title' => 'Module'])->id, 'title' => 'Lesson'])->id,
            'progress_status' => ProgressStatus::Completed,
        ];
    }
}
