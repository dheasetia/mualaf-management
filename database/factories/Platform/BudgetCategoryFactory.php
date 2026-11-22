<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Domain\Platform\Models\BudgetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetCategory> */
final class BudgetCategoryFactory extends Factory
{
    protected $model = BudgetCategory::class;

    public function definition(): array
    {
        return [
            'code' => str_replace('-', '_', fake()->unique()->slug(2)),
            'name_ar' => 'بند '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->word(),
        ];
    }
}
