<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Domain\Platform\Models\City;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tenant> */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $city = City::factory()->create();

        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name_ar' => 'المركز الإسلامي '.fake()->unique()->numberBetween(1, 99999),
            'name_en' => fake()->unique()->city().' Islamic Center',
            'region_id' => $city->region_id,
            'city_id' => $city->id,
            'status' => TenantStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Suspended,
            'activated_at' => now()->subMonth(),
            'suspended_at' => now(),
        ]);
    }
}
