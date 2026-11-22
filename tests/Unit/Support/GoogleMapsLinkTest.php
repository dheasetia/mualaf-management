<?php

declare(strict_types=1);

use App\Support\Geo\GoogleMapsLink;

it('reads coordinates from the common Google Maps URL forms', function (string $url, float $latitude, float $longitude): void {
    expect(GoogleMapsLink::coordinates($url))->toBe(['latitude' => $latitude, 'longitude' => $longitude]);
})->with([
    'place with @' => ['https://www.google.com/maps/place/Dammam/@26.4207,50.0888,13z', 26.4207, 50.0888],
    'search query' => ['https://www.google.com/maps/search/?api=1&query=24.7136,46.6753', 24.7136, 46.6753],
    'q parameter' => ['https://maps.google.com/?q=21.4858,39.1925', 21.4858, 39.1925],
    'data !3d !4d' => ['https://www.google.com/maps/place/X/data=!3d26.43!4d50.10', 26.43, 50.10],
]);

it('rejects other hosts, plain http and URLs without coordinates', function (string $url): void {
    expect(GoogleMapsLink::coordinates($url))->toBeNull();
})->with([
    'other host' => ['https://evil.example.com/maps/@26.4,50.0'],
    'http' => ['http://www.google.com/maps/@26.4,50.0,13z'],
    'short link' => ['https://maps.app.goo.gl/AbCdEf123'],
]);

it('accepts short links as Google Maps URLs and builds a viewing link', function (): void {
    expect(GoogleMapsLink::isGoogleMapsUrl('https://maps.app.goo.gl/AbCdEf123'))->toBeTrue()
        ->and(GoogleMapsLink::forCoordinates(26.4207, 50.0888))->toBe('https://www.google.com/maps/search/?api=1&query=26.4207%2C50.0888')
        ->and(GoogleMapsLink::forCoordinates(null, 50.0))->toBeNull();
});
