<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Filament\Auth\EditProfile;
use Filament\Facades\Filament;
use Filament\Panel;

/**
 * The profile page inside an office (/center/{office}/profile), so the sidebar
 * stays in view. Filament's own profile route has no office, and the Center
 * sidebar needs one; it stays for links that carry no office.
 */
final class OfficeProfile extends EditProfile
{
    protected static bool $isDiscovered = true;

    protected static ?string $slug = 'profile';

    protected static bool $shouldRegisterNavigation = false;

    public static function isSimple(): bool
    {
        return false;
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'office-profile';
    }

    public static function getRouteName(?Panel $panel = null): string
    {
        return ($panel ?? Filament::getCurrentOrDefaultPanel())->generateRouteName('office-profile');
    }
}
