<?php

declare(strict_types=1);

namespace Database\Factories\FollowUp;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\FollowUp\Enums\FollowUpState;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Models\ConvertFollowUpStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ConvertFollowUpStatus>
 */
final class ConvertFollowUpStatusFactory extends Factory
{
    protected $model = ConvertFollowUpStatus::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'primary_dai_id' => Dai::factory(),
            'status' => FollowUpState::Stable,
            'follow_up_priority' => Priority::Normal,
            'recorded_by' => User::factory(),
        ];
    }
}
