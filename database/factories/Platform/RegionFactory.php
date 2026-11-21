<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Domain\Platform\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Region> */
final class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('R??##')),
            'name_ar' => 'منطقة '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->city().' Region',
            'sort_order' => 0,
        ];
    }
}
