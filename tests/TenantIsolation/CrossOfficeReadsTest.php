<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;

// Regression: a Filament tenancy scope on Convert, added during a Center panel
// request, survived TenantContext::bypass() and hid other offices' converts
// from the national search and duplicate screening.
it('still reads converts of other offices through bypass after a Center panel request', function (): void {
    seedRbac();
    [$dammam, $riyadh] = [Tenant::factory()->active()->create(['code' => 'DMM']), Tenant::factory()->active()->create(['code' => 'RUH'])];
    $riyadhConvert = inTenant($riyadh, fn () => Convert::factory()->create());

    $this->actingAs(withAuthenticator(grantRole(memberOf($dammam), 'head_of_dawah', $dammam)))
        ->get('/center/DMM/converts')
        ->assertOk();

    inTenant($dammam, function () use ($riyadhConvert): void {
        expect(Convert::query()->find($riyadhConvert->id))->toBeNull()
            ->and(app(TenantContext::class)->bypass('test', fn () => Convert::query()->find($riyadhConvert->id)))->not->toBeNull();
    });
})->group('tenant-isolation');
