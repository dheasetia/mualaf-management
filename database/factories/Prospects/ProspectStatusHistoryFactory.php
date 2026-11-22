<?php

declare(strict_types=1);

namespace Database\Factories\Prospects;

use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Models\ProspectStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only; the application writes through ProspectStateMachine. Use inside inTenant().
 *
 * @extends Factory<ProspectStatusHistory>
 */
final class ProspectStatusHistoryFactory extends Factory
{
    protected $model = ProspectStatusHistory::class;

    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::factory(),
            'to_status' => ProspectStatus::New,
            'changed_by' => User::factory(),
        ];
    }
}
