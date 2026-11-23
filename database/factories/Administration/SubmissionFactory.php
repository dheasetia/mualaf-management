<?php

declare(strict_types=1);

namespace Database\Factories\Administration;

use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Models\SubmissionType;
use App\Domain\Converts\Models\Convert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only (a draft). Use inside inTenant().
 *
 * @extends Factory<Submission>
 */
final class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'submission_type_id' => SubmissionType::factory(),
            'reference_no' => 'T-SUB-'.fake()->unique()->numerify('########'),
            'status' => SubmissionStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
