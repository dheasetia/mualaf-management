<?php

declare(strict_types=1);

namespace App\Support\Identity;

use RuntimeException;

/**
 * Keyed, non-reversible lookups for sensitive values (04-identity-privacy §4.2):
 * equal inputs give equal 32-byte fingerprints, but the value cannot be
 * recovered and the fingerprint cannot be recomputed without the key.
 */
final class Fingerprint
{
    /**
     * @param  string  $purpose  domain separation, such as "phone" or "iqamah|SA"
     */
    public static function make(string $purpose, string $normalized): string
    {
        return hash_hmac('sha256', $purpose.'|'.$normalized, self::key(), binary: true);
    }

    private static function key(): string
    {
        $key = config('identity.hmac_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('IDENTITY_HMAC_KEY is not set.');
        }

        return str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7), true) : $key;
    }
}
