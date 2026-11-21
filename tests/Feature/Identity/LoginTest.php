<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\DeactivateUser;
use App\Domain\Identity\Notifications\AccountLockedNotification;
use App\Domain\Identity\Services\AccountLockout;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Auth\Login;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('center'));
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->user = memberOf($this->tenant);
    $this->user->forceFill(['email' => 'officer@muallaf.test', 'password' => 'correct-horse-42'])->save();
});

function attemptLogin(string $email, string $password): Testable
{
    return Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate');
}

it('signs in and records the login time, address and session start', function (): void {
    attemptLogin('officer@muallaf.test', 'correct-horse-42')->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->user);
    $this->user->refresh();

    expect($this->user->last_login_at)->not->toBeNull()
        ->and($this->user->last_login_ip)->toBe('127.0.0.1')
        ->and(session(EnforceAbsoluteSessionLifetime::SESSION_KEY))->toBeInt();
});

it('does not offer a remember-me option', function (): void {
    Livewire::test(Login::class)->assertFormFieldDoesNotExist('remember');
});

it('rejects a deactivated user even with the right password', function (): void {
    app(DeactivateUser::class)->handle($this->user);

    attemptLogin('officer@muallaf.test', 'correct-horse-42')->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('locks the account after five failures and notifies the owner once', function (): void {
    Notification::fake();

    foreach (range(1, AccountLockout::MAX_ATTEMPTS) as $attempt) {
        attemptLogin('officer@muallaf.test', 'wrong-password-'.$attempt)->assertHasFormErrors(['email']);
    }

    attemptLogin('officer@muallaf.test', 'correct-horse-42')
        ->assertHasErrors(['data.email' => [__('identity.locked.message', ['minutes' => 15])]]);

    $this->assertGuest();
    Notification::assertSentToTimes($this->user, AccountLockedNotification::class, 1);
});

it('unlocks the account after fifteen minutes', function (): void {
    foreach (range(1, AccountLockout::MAX_ATTEMPTS) as $attempt) {
        attemptLogin('officer@muallaf.test', 'wrong-password-'.$attempt);
    }

    $this->travel(16)->minutes();

    attemptLogin('officer@muallaf.test', 'correct-horse-42')->assertHasNoFormErrors();
    $this->assertAuthenticatedAs($this->user);
});

it('locks unknown emails the same way so responses do not reveal accounts', function (): void {
    Notification::fake();

    foreach (range(1, AccountLockout::MAX_ATTEMPTS) as $attempt) {
        attemptLogin('nobody@muallaf.test', 'wrong-password-'.$attempt);
    }

    attemptLogin('nobody@muallaf.test', 'anything-123456')
        ->assertHasErrors(['data.email' => [__('identity.locked.message', ['minutes' => 15])]]);

    Notification::assertNothingSent();
});
