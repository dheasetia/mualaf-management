<?php

declare(strict_types=1);

namespace App\Domain\Converts\Services;

use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Exceptions\IdentityRuleViolation;
use App\Support\Identity\Digits;

/**
 * Normalisation and validation of identity numbers (04-identity-privacy §4.2),
 * so one document always yields one fingerprint however it is typed.
 */
final class IdentityNumber
{
    public static function normalize(IdentityType $type, string $raw): string
    {
        $value = strtoupper(preg_replace('/[\s\-.\/]+/u', '', Digits::toAscii(trim($raw))) ?? '');

        $valid = match ($type) {
            IdentityType::NationalId => self::isSaudiId($value, '1'),
            IdentityType::Iqamah => self::isSaudiId($value, '2'),
            IdentityType::Passport => preg_match('/^[A-Z0-9]{5,20}$/', $value) === 1,
            IdentityType::GccId, IdentityType::BorderNumber => preg_match('/^[0-9]{6,15}$/', $value) === 1,
            IdentityType::Other => preg_match('/^[A-Z0-9]{3,30}$/', $value) === 1,
        };

        if (! $valid) {
            throw IdentityRuleViolation::invalidNumber();
        }

        return $value;
    }

    /** Ten digits, the given first digit (1 citizen, 2 resident) and a valid Luhn check digit. */
    private static function isSaudiId(string $value, string $firstDigit): bool
    {
        return preg_match('/^[0-9]{10}$/', $value) === 1 && $value[0] === $firstDigit && self::passesLuhn($value);
    }

    public static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    /** Last four characters only, as stored in identity_last4: "••••••4512". */
    public static function mask(string $last4): string
    {
        return str_repeat('•', 6).$last4;
    }
}
