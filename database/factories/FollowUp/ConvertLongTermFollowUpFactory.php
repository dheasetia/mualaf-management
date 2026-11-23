<?php

declare(strict_types=1);

namespace Database\Factories\FollowUp;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Models\Dai;
use App\Domain\FollowUp\Enums\ContactedPerson;
use App\Domain\FollowUp\Enums\ContactMethod;
use App\Domain\FollowUp\Enums\ContactResult;
use App\Domain\FollowUp\Models\ConvertLongTermFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ConvertLongTermFollowUp>
 */
final class ConvertLongTermFollowUpFactory extends Factory
{
    protected $model = ConvertLongTermFollowUp::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'dai_id' => Dai::factory(),
            'follow_up_at' => now(),
            'contact_method' => ContactMethod::Phone,
            'contacted_person_type' => ContactedPerson::Convert,
            'contact_result' => ContactResult::Reached,
            'confidentiality_level' => Confidentiality::Normal,
            'recorded_by' => User::factory(),
        ];
    }
}
