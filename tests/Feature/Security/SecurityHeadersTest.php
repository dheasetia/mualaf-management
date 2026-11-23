<?php

declare(strict_types=1);

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
