<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Minimal regions and cities needed by tenants. The full national list is loaded
 * with the master-data module (implementation step 6).
 */
final class PlatformMasterSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: array<string, array{0: string, 1: string}>}> */
    private const array REGIONS = [
        'EP' => ['المنطقة الشرقية', 'Eastern Province', [
            'DMM' => ['الدمام', 'Dammam'],
            'KHB' => ['الخبر', 'Khobar'],
            'DHA' => ['الظهران', 'Dhahran'],
        ]],
        'RD' => ['منطقة الرياض', 'Riyadh Region', [
            'RUH' => ['الرياض', 'Riyadh'],
        ]],
        'MK' => ['منطقة مكة المكرمة', 'Makkah Region', [
            'JED' => ['جدة', 'Jeddah'],
            'MKK' => ['مكة المكرمة', 'Makkah'],
        ]],
        'MD' => ['منطقة المدينة المنورة', 'Madinah Region', [
            'MED' => ['المدينة المنورة', 'Madinah'],
        ]],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::REGIONS as $regionCode => [$regionAr, $regionEn, $cities]) {
            $region = Region::query()->updateOrCreate(
                ['code' => $regionCode],
                ['name_ar' => $regionAr, 'name_en' => $regionEn, 'sort_order' => $order++],
            );

            foreach ($cities as $cityCode => [$cityAr, $cityEn]) {
                City::query()->updateOrCreate(
                    ['code' => $cityCode],
                    ['region_id' => $region->id, 'name_ar' => $cityAr, 'name_en' => $cityEn],
                );
            }
        }
    }
}
