<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Domain\Platform\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Language> */
final class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??'),
            'name_ar' => 'لغة '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->word(),
            'is_rtl' => false,
        ];
    }
}
