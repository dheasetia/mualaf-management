<?php

declare(strict_types=1);

use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Region;
use Database\Seeders\PlatformMasterSeeder;

it('seeds all 13 regions, every ISO country and the PRD activity types', function (): void {
    $this->seed(PlatformMasterSeeder::class);

    expect(Region::query()->count())->toBe(13)
        ->and(Country::query()->count())->toBe(249)
        ->and(Country::query()->where('iso2', 'SA')->sole())
        ->iso3->toBe('SAU')->name_ar->toBe('المملكة العربية السعودية')->phone_code->toBe('+966')
        ->and(Language::query()->where('code', 'ur')->sole()->is_rtl)->toBeTrue()
        ->and(DaiActivityType::query()->count())->toBe(23)
        ->and(DaiActivityType::query()->where('requires_verification', true)->pluck('code')->all())->toBe(['shahadah']);
});

it('does not undo changes administrators made when it runs again', function (): void {
    $this->seed(PlatformMasterSeeder::class);
    Region::query()->where('code', 'EP')->update(['name_en' => 'Eastern Region']);
    City::query()->where('code', 'KHB')->sole()->delete();
    $counts = [Region::withTrashed()->count(), City::withTrashed()->count(), Country::withTrashed()->count()];

    $this->seed(PlatformMasterSeeder::class);

    expect(Region::query()->where('code', 'EP')->value('name_en'))->toBe('Eastern Region')
        ->and(City::query()->where('code', 'KHB')->exists())->toBeFalse()
        ->and([Region::withTrashed()->count(), City::withTrashed()->count(), Country::withTrashed()->count()])->toBe($counts);
});
