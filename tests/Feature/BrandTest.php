<?php

declare(strict_types=1);

it('shows the Muallaf Center logo on both panels', function (string $loginUrl): void {
    $this->get($loginUrl)
        ->assertOk()
        ->assertSee(asset('images/brand/logo.png'))
        ->assertSee(asset('images/brand/logo-white.png'))
        ->assertSee('مركز رعاية المسلمين الجدد');
})->with(['center' => '/center/login', 'central' => '/central/login']);
