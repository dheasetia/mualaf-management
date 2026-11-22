<?php

declare(strict_types=1);

namespace Database\Factories\Dawah;

use App\Domain\Dawah\Enums\MaterialStatus;
use App\Domain\Dawah\Enums\MaterialType;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Domain\Platform\Models\Language;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only: a national approved leaflet unless tenant_id is given.
 *
 * @extends Factory<DawahMaterial>
 */
final class DawahMaterialFactory extends Factory
{
    protected $model = DawahMaterial::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'material_type' => MaterialType::Leaflet,
            'language_id' => Language::factory(),
            'version' => '1',
            'status' => MaterialStatus::Approved,
            'created_by' => User::factory(),
        ];
    }
}
