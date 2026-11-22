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
            // ISO 3166 user-assigned codes (QM–QZ, XA–XZ): never a real country such as SA.
            'iso2' => fake()->unique()->randomElement([...array_map(fn (string $l): string => 'Q'.$l, range('M', 'Z')), ...array_map(fn (string $l): string => 'X'.$l, range('A', 'Z'))]),
            'iso3' => 'X'.strtoupper(fake()->unique()->lexify('??')),
            'name_ar' => 'دولة '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->country(),
        ];
    }
}
