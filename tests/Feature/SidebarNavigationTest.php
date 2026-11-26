<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;

it('orders the center sidebar groups', function (string $locale): void {
    seedRbac();
    $tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    inCenterPanel($tenant, grantRole(memberOf($tenant), 'center_admin', $tenant));
    app()->setLocale($locale);

    $groups = collect(Filament::getNavigation())
        ->map(fn (NavigationGroup $group): ?string => $group->getLabel())
        ->filter()
        ->values()
        ->all();

    $expected = array_map(fn (string $key): string => __($key), [
        'dawah.navigation_group', 'guidance.navigation_group', 'submissions.navigation_group',
        'reports.navigation_group', 'transport.navigation_group', 'pilgrimage.navigation_group',
    ]);

    expect(array_values(array_intersect($groups, $expected)))->toBe($expected);
})->with(['ar', 'en']);
