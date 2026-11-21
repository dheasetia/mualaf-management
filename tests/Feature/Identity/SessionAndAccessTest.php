<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\ActivateUser;
use App\Domain\Identity\Actions\DeactivateUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->user = memberOf($this->tenant);
});

it('keeps a session that is within the absolute lifetime', function (): void {
    $this->actingAs($this->user)
        ->withSession([EnforceAbsoluteSessionLifetime::SESSION_KEY => now()->subHours(11)->getTimestamp()])
        ->get('/center/DMM')
        ->assertOk();
});

it('ends a session older than the absolute lifetime even when active', function (): void {
    $this->actingAs($this->user)
        ->withSession([EnforceAbsoluteSessionLifetime::SESSION_KEY => now()->subHours(13)->getTimestamp()])
        ->get('/center/DMM')
        ->assertRedirect('/center/login');

    $this->assertGuest();
});

it('blocks a deactivated user on the next request and restores access on activation', function (): void {
    $this->actingAs($this->user)->get('/center/DMM')->assertOk();

    app(DeactivateUser::class)->handle($this->user);
    $this->actingAs($this->user->refresh())->get('/center/DMM')->assertForbidden();

    app(ActivateUser::class)->handle($this->user);
    $this->actingAs($this->user->refresh())->get('/center/DMM')->assertOk();
});
