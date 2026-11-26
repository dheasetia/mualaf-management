<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Vite;

it('sends the security headers on web pages', function (): void {
    $response = $this->get('http://localhost/');

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');

    $policy = (string) $response->headers->get('Content-Security-Policy');
    expect($policy)->toContain("frame-ancestors 'none'")->toContain("object-src 'none'")->toContain("form-action 'self'")
        ->and((string) $response->headers->get('Permissions-Policy'))->toContain('geolocation=()')
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('adds HSTS over HTTPS', function (): void {
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('also protects the public certificate verification page', function (): void {
    $this->get('/verify/'.fake()->uuid().'?t=x')->assertOk()->assertHeader('X-Frame-Options', 'DENY');
});

it('allows only nonced inline scripts, and gives Livewire and Filament the nonce', function (): void {
    $response = $this->get('/center/login')->assertOk();

    $policy = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/script-src 'self' 'nonce-([A-Za-z0-9]+)'/", $policy, $match);

    expect($match)->toHaveCount(2)
        ->and($policy)->not->toContain("script-src 'self' 'unsafe-inline'")
        ->toContain("script-src-attr 'none'")
        ->and($response->getContent())->toContain('nonce="'.$match[1].'"')
        ->not->toMatch('/<script(?![^>]*\bnonce=)(?![^>]*\bsrc=)[^>]*>/');

    // A new nonce on every response.
    expect((string) $this->get('/center/login')->headers->get('Content-Security-Policy'))->not->toContain($match[1]);
});

it('sends the nonced policy on signed-in panel pages, which carry no un-nonced scripts or inline handlers', function (): void {
    seedRbac();
    $tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $officer = withAuthenticator(grantRole(memberOf($tenant), 'transportation_officer', $tenant));

    $response = $this->actingAs($officer)->get('/center/DMM/trips')->assertOk()->assertHeader('X-Frame-Options', 'DENY');

    preg_match("/script-src 'self' 'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);

    expect($match)->toHaveCount(2)
        ->and($response->getContent())->toContain('nonce="'.$match[1].'"')
        ->not->toMatch('/<script(?![^>]*\bnonce=)(?![^>]*\bsrc=)[^>]*>/')
        ->not->toMatch('/<[^>]+\son[a-z]+\s*=/i');
});

it('allows the Vite dev server from the hot file, locally only', function (): void {
    $hotFile = tempnam(sys_get_temp_dir(), 'hot');
    file_put_contents($hotFile, 'https://muallaf-management.test:5173');
    Vite::useHotFile($hotFile);

    $productionPolicy = (string) $this->get('/center/login')->headers->get('Content-Security-Policy');

    app()->detectEnvironment(fn (): string => 'local');
    $localPolicy = (string) $this->get('/center/login')->headers->get('Content-Security-Policy');
    unlink($hotFile);

    expect($productionPolicy)->not->toContain(':5173')
        ->and($localPolicy)->toContain("style-src 'self' 'unsafe-inline' ")
        ->toContain('https://muallaf-management.test:5173')
        ->toContain('wss://muallaf-management.test:5173')
        ->toContain('http://localhost:5173');
});
