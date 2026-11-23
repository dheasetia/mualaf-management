<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Google Maps links without an API key: coordinates are read from a pasted
 * Google Maps URL, and a viewing link is built from coordinates.
 */
final class GoogleMapsLink
{
    private const string HOST_PATTERN = '/^(www\.)?(google\.[a-z.]+|maps\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl)$/i';

    public static function isGoogleMapsUrl(?string $url): bool
    {
        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        return ($parts['scheme'] ?? '') === 'https' && preg_match(self::HOST_PATTERN, $host) === 1;
    }

    /**
     * Coordinates in "@24.71,46.67", "?q=24.71,46.67", "query=..." or
     * "!3d24.71!4d46.67" forms; null when the URL carries none (short links).
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public static function coordinates(?string $url): ?array
    {
        if (! self::isGoogleMapsUrl($url)) {
            return null;
        }

        $decoded = urldecode((string) $url);
        $patterns = [
            '/@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/',
            '/[?&](?:q|query|ll|center)=(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/',
            '/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $match) === 1) {
                $latitude = (float) $match[1];
                $longitude = (float) $match[2];

                if (abs($latitude) <= 90 && abs($longitude) <= 180) {
                    return ['latitude' => round($latitude, 7), 'longitude' => round($longitude, 7)];
                }
            }
        }

        return null;
    }

    public static function forCoordinates(float|string|null $latitude, float|string|null $longitude): ?string
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($latitude.','.$longitude);
    }
}
