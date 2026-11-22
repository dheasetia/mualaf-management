<?php

declare(strict_types=1);

namespace App\Support\Identity;

/**
 * Normalises phone numbers to international digits without "+", so the same
 * number typed in different ways gets the same fingerprint.
 */
final class PhoneNumber
{
    private const string SAUDI_CALLING_CODE = '966';

    /**
     * "+966 50-123 4567", "00966501234567", "0501234567" and "٠٥٠١٢٣٤٥٦٧" all give
     * "966501234567". Local numbers without a country code are taken as Saudi.
     * Returns null when too few digits remain to be a phone number.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', Digits::toAscii($phone)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = self::SAUDI_CALLING_CODE.substr($digits, 1);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            $digits = self::SAUDI_CALLING_CODE.$digits;
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function fingerprint(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized === null ? null : Fingerprint::make('phone', $normalized);
    }
}
