<?php

declare(strict_types=1);

namespace Database\Factories\FollowUp;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ConvertFollowUpSchedule>
 */
final class ConvertFollowUpScheduleFactory extends Factory
{
    protected $model = ConvertFollowUpSchedule::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'dai_id' => Dai::factory(),
            'scheduled_at' => now()->addWeek(),
            'follow_up_type' => 'long_term',
            'priority' => Priority::Normal,
            'status' => ScheduleStatus::Scheduled,
        ];
    }
}
