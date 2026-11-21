<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\MissingTenantContext;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;

it('starts without a tenant and fails closed when one is required', function (): void {
    $context = app(TenantContext::class);

    expect($context->has())->toBeFalse()
        ->and(fn () => $context->requireId())->toThrow(MissingTenantContext::class);
});

it('runs a callback in a tenant and restores the previous context', function (): void {
    [$dammam, $riyadh] = Tenant::factory()->active()->count(2)->create();
    $context = app(TenantContext::class);
    $context->set($dammam);

    $inside = $context->run($riyadh, fn (): ?int => $context->id());

    expect($inside)->toBe($riyadh->id)
        ->and($context->id())->toBe($dammam->id);
});

it('clears the context after run when none was set before', function (): void {
    $tenant = Tenant::factory()->active()->create();
    $context = app(TenantContext::class);

    $context->run($tenant, fn () => null);

    expect($context->has())->toBeFalse();
});
