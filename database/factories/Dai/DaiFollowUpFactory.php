<?php

declare(strict_types=1);

namespace Database\Factories\Dai;

use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\FollowUpType;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only; the application writes through RecordFollowUp. Use inside inTenant().
 *
 * @extends Factory<DaiFollowUp>
 */
final class DaiFollowUpFactory extends Factory
{
    protected $model = DaiFollowUp::class;

    public function definition(): array
    {
        return [
            'dai_id' => Dai::factory(),
            'subject_type' => 'prospect',
            'subject_id' => fake()->numberBetween(1, 1_000_000),
            'follow_up_type' => FollowUpType::Visit,
            'contact_method' => ContactMethod::InPerson,
            'occurred_at' => now(),
            'summary' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
