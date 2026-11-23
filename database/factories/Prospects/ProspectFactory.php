<?php

declare(strict_types=1);

namespace Database\Factories\Prospects;

use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only: no assignment or history rows (use CreateProspect for those).
 * Use inside inTenant().
 *
 * @extends Factory<Prospect>
 */
final class ProspectFactory extends Factory
{
    protected $model = Prospect::class;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'gender' => Gender::Male,
            'source_type' => ProspectSource::WalkIn,
            'status' => ProspectStatus::New,
            'primary_dai_id' => Dai::factory(),
            'created_by' => User::factory(),
        ];
    }

    public function status(ProspectStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
