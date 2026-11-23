<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * The interface languages: Arabic (default, RTL) and English.
 */
final class Locales
{
    public const array SUPPORTED = ['ar', 'en'];

    public static function isSupported(?string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    /** The language to offer in the switcher. */
    public static function other(string $locale): string
    {
        return $locale === 'en' ? 'ar' : 'en';
    }

    /** The language's own name, shown on the switcher. */
    public static function nativeName(string $locale): string
    {
        return $locale === 'en' ? 'English' : 'العربية';
    }
}
