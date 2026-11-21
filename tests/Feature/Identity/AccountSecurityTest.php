<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
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

it('requires multi-factor authentication on the central panel', function (): void {
    expect(Filament::getPanel('central')->isMultiFactorAuthenticationRequired())->toBeTrue()
        ->and(Filament::getPanel('center')->getMultiFactorAuthenticationProviders())->not->toBeEmpty();
});

it('stores the authenticator secret and recovery codes encrypted', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

    $row = DB::table('users')->where('id', $user->id)->first();

    expect($row->app_authentication_secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($row->app_authentication_recovery_codes)->not->toContain('code-one')
        ->and($user->refresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->getAppAuthenticationRecoveryCodes())->toBe(['code-one', 'code-two']);
});
