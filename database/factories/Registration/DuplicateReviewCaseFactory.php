<?php

declare(strict_types=1);

namespace Database\Factories\Registration;

use App\Domain\Converts\Models\Convert;
use App\Domain\Registration\Enums\CandidateType;
use App\Domain\Registration\Enums\MatchReason;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only; cases are opened by DuplicateScreen. Use inside inTenant().
 *
 * @extends Factory<DuplicateReviewCase>
 */
final class DuplicateReviewCaseFactory extends Factory
{
    protected $model = DuplicateReviewCase::class;

    public function definition(): array
    {
        return [
            'candidate_type' => CandidateType::RegistrationDraft,
            'candidate_key' => hash('sha256', fake()->uuid()),
            'possible_convert_id' => Convert::factory(),
            'match_reason' => MatchReason::FuzzyNameDob,
            'match_score' => 0.9,
            'match_details' => ['candidate_name' => fake()->name()],
            'created_by' => User::factory(),
        ];
    }
}
