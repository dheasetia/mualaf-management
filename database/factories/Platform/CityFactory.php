<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<City> */
final class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'code' => strtoupper(fake()->unique()->bothify('C??###')),
            'name_ar' => 'مدينة '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->city(),
        ];
    }
}
