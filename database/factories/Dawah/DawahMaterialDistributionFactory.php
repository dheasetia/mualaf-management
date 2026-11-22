<?php

declare(strict_types=1);

namespace Database\Factories\Dawah;

use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Domain\Dawah\Models\DawahMaterialDistribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<DawahMaterialDistribution>
 */
final class DawahMaterialDistributionFactory extends Factory
{
    protected $model = DawahMaterialDistribution::class;

    public function definition(): array
    {
        return [
            'dai_id' => Dai::factory(),
            'material_id' => DawahMaterial::factory(),
            'quantity' => 1,
            'distributed_at' => now(),
        ];
    }
}
