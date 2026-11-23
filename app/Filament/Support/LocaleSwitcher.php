<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Localization\Locales;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * User-menu entry that switches between Arabic and English.
 */
final class LocaleSwitcher
{
    public static function userMenuItem(): Action
    {
        return Action::make('switchLocale')
            ->label(fn (): string => Locales::nativeName(Locales::other(app()->getLocale())))
            ->icon(Heroicon::OutlinedLanguage)
            ->url(fn (): string => route('locale.switch', Locales::other(app()->getLocale())))
            ->postToUrl();
    }
}
