<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Domain\Platform\Models\City;
use App\Domain\Tenancy\Actions\ChangeTenantStatus;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Domain\Tenancy\Actions\GrantTenantMembership;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Synthetic local data only. Never run against staging or production data.
 */
final class DevelopmentSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> */
    private const array TENANTS = [
        'DMM' => ['المركز الإسلامي بالدمام', 'Islamic Center Dammam'],
        'RUH' => ['المركز الإسلامي بالرياض', 'Islamic Center Riyadh'],
        'JED' => ['المركز الإسلامي بجدة', 'Islamic Center Jeddah'],
    ];

    public function run(
        CreateTenant $createTenant,
        ChangeTenantStatus $changeStatus,
        GrantTenantMembership $grant,
        AssignRole $assignRole,
    ): void {
        $password = config('app.dev_seed_password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set DEV_SEED_PASSWORD (12+ characters) in .env before seeding development data.');
        }

        $this->call([PlatformMasterSeeder::class, RbacSeeder::class]);

        $user = User::query()->firstOrCreate(
            ['email' => 'dev.admin@muallaf.test'],
            ['name' => 'Dev Admin', 'password' => $password],
        );

        foreach (self::TENANTS as $code => [$nameAr, $nameEn]) {
            if (Tenant::query()->where('code', $code)->exists()) {
                continue;
            }

            $city = City::query()->where('code', $code)->firstOrFail();

            $tenant = $createTenant->handle([
                'code' => $code,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'region_id' => $city->region_id,
                'city_id' => $city->id,
            ]);

            $changeStatus->handle($tenant, TenantStatus::Active);
            $grant->handle($user, $tenant);
            $assignRole->handle($user, Role::query()->where('code', 'center_admin')->sole(), $tenant, null);

            // One synthetic user per office role, e.g. dai.dmm@muallaf.test.
            foreach (Role::query()->where('scope_type', RoleScope::Tenant)->where('code', '!=', 'center_admin')->get() as $role) {
                $staff = User::query()->create([
                    'name' => $role->name_en.' '.$code,
                    'email' => sprintf('%s.%s@muallaf.test', str_replace('_', '-', $role->code), strtolower($code)),
                    'password' => $password,
                ]);
                $grant->handle($staff, $tenant);
                $assignRole->handle($staff, $role, $tenant, null);
            }
        }

        if (! $user->roleAssignments()->whereNull('tenant_id')->exists()) {
            $assignRole->handle($user, Role::query()->where('code', 'super_admin')->sole(), null, null);
        }
    }
}
