<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Domain\Platform\Enums\DaiActivityCategory;
use App\Domain\Platform\Models\DaiActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DaiActivityType> */
final class DaiActivityTypeFactory extends Factory
{
    protected $model = DaiActivityType::class;

    public function definition(): array
    {
        return [
            'code' => str_replace('-', '_', fake()->unique()->slug(2)),
            'name_ar' => 'نشاط '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->words(2, true),
            'category' => fake()->randomElement(DaiActivityCategory::cases()),
        ];
    }
}
