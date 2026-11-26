<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

it('gives every user a uuid', function (): void {
    expect(User::factory()->create()->uuid)->toBeString()->toHaveLength(36);
});

it('enforces the password policy', function (string $password, bool $valid): void {
    $validator = Validator::make(['password' => $password], ['password' => [Password::defaults()]]);

    expect($validator->passes())->toBe($valid);
})->with([
    'too short' => ['short1', false],
    'no digit' => ['abcdefghijklm', false],
    'no letter' => ['123456789012', false],
    'twelve chars with letters and digits' => ['abcdefghijk1', true],
]);

it('signs in with a password only, without an authenticator step', function (): void {
    expect(Filament::getPanel('central')->hasMultiFactorAuthentication())->toBeFalse()
        ->and(Filament::getPanel('center')->hasMultiFactorAuthentication())->toBeFalse();
});
