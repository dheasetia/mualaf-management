<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Support\Exceptions\RuleViolation;
use Closure;

/**
 * Coordinates of a place from form state: typed latitude/longitude win;
 * otherwise they are read from the Google Maps URL when it carries them.
 */
final class Location
{
    /**
     * @param  Closure(): RuleViolation  $invalidUrl
     * @return array{latitude: ?string, longitude: ?string, google_maps_url: ?string}
     */
    public static function resolve(mixed $latitude, mixed $longitude, mixed $url, Closure $invalidUrl): array
    {
        $url = blank($url) ? null : trim((string) $url);

        if ($url !== null && ! GoogleMapsLink::isGoogleMapsUrl($url)) {
            throw $invalidUrl();
        }

        if (blank($latitude) || blank($longitude)) {
            $fromUrl = GoogleMapsLink::coordinates($url);
            $latitude = $fromUrl['latitude'] ?? null;
            $longitude = $fromUrl['longitude'] ?? null;
        }

        return [
            'latitude' => blank($latitude) ? null : (string) $latitude,
            'longitude' => blank($longitude) ? null : (string) $longitude,
            'google_maps_url' => $url,
        ];
    }
}
