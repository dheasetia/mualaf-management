<?php

declare(strict_types=1);

namespace Database\Factories\Converts;

use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Platform\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data with a random fingerprint; use IdentityRegistry::add() when the
 * fingerprint must match a number. Use inside inTenant().
 *
 * @extends Factory<ConvertIdentifier>
 */
final class ConvertIdentifierFactory extends Factory
{
    protected $model = ConvertIdentifier::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'identity_type' => IdentityType::Passport,
            'identity_number_encrypted' => 'P'.fake()->numerify('#######'),
            'identity_last4' => fake()->numerify('####'),
            'identity_fingerprint' => random_bytes(32),
            'fingerprint_key_version' => 1,
            'issuing_country_id' => Country::factory(),
            'created_by' => User::factory(),
        ];
    }
}
