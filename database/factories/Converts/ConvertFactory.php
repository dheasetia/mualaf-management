<?php

declare(strict_types=1);

namespace Database\Factories\Converts;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only; converts are created by the shahadah workflow (step 11).
 * Origin and current office come from the tenant in context: use inside inTenant().
 *
 * @extends Factory<Convert>
 */
final class ConvertFactory extends Factory
{
    protected $model = Convert::class;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'gender' => Gender::Male,
            'nationality_id' => Country::factory(),
            'primary_dai_id' => Dai::factory(),
            'created_by' => User::factory(),
        ];
    }
}
