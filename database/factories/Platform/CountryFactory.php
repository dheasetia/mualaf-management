<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Domain\Platform\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Country> */
final class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'iso3' => strtoupper(fake()->unique()->lexify('???')),
            'name_ar' => 'دولة '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->country(),
        ];
    }
}
