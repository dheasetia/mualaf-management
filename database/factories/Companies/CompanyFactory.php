<?php

declare(strict_types=1);

namespace Database\Factories\Companies;

use App\Domain\Companies\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Owned by the tenant in context (national without context).
 *
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'شركة '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->company(),
            'registration_number' => fake()->unique()->numerify('10########'),
            'created_by' => User::factory(),
        ];
    }
}
