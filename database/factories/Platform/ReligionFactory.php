<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Domain\Platform\Models\Religion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Religion> */
final class ReligionFactory extends Factory
{
    protected $model = Religion::class;

    public function definition(): array
    {
        return [
            'code' => str_replace('-', '_', fake()->unique()->slug(2)),
            'name_ar' => 'ديانة '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->word(),
        ];
    }
}
