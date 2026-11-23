<?php

declare(strict_types=1);

namespace App\Support\Dates;

use DateTimeInterface;
use IntlDateFormatter;

/**
 * Umm al-Qura dates for printed documents (13-infrastructure-security §13.2).
 */
final class HijriDate
{
    public static function format(DateTimeInterface $date, string $locale = 'ar'): string
    {
        $formatter = new IntlDateFormatter(
            $locale.'_SA@calendar=islamic-umalqura',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            (string) config('app.display_timezone'),
            IntlDateFormatter::TRADITIONAL,
        );

        return (string) $formatter->format($date);
    }

    public static function gregorian(DateTimeInterface $date, string $locale = 'ar'): string
    {
        $formatter = new IntlDateFormatter(
            $locale.'_SA@calendar=gregorian',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            (string) config('app.display_timezone'),
            IntlDateFormatter::GREGORIAN,
        );

        return (string) $formatter->format($date);
    }
}
