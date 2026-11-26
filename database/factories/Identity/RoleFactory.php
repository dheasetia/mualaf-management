<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'code' => 'test_role_'.fake()->unique()->numberBetween(1, 999999),
            'name_ar' => 'دور تجريبي',
            'name_en' => 'Test role',
            'scope_type' => RoleScope::Tenant,
            'is_system' => false,
        ];
    }
}
