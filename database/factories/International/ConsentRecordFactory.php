<?php

declare(strict_types=1);

namespace Database\Factories\International;

use App\Domain\Converts\Models\Convert;
use App\Domain\International\Enums\ConsentMethod;
use App\Domain\International\Enums\ConsentType;
use App\Domain\International\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ConsentRecord>
 */
final class ConsentRecordFactory extends Factory
{
    protected $model = ConsentRecord::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'consent_type' => ConsentType::InternationalSharing,
            'scope' => ['fields' => ['name', 'islamic_name']],
            'given_at' => now(),
            'method' => ConsentMethod::Written,
            'recorded_by' => User::factory(),
        ];
    }
}
