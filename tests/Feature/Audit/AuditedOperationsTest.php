<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Actions\RevokeRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Auth\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('center'));
    $this->tenant = Tenant::factory()->active()->create();
    $this->user = memberOf($this->tenant);
    $this->user->forceFill(['email' => 'officer@muallaf.test', 'password' => 'correct-horse-42'])->save();
});

function auditEntry(string $action): AuditLog
{
    return AuditLog::query()->where('action', $action)->sole();
}

it('audits a successful sign-in with the user as actor and subject', function (): void {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'officer@muallaf.test', 'password' => 'correct-horse-42'])
        ->call('authenticate');

    expect(auditEntry('auth.login'))
        ->actor_id->toBe($this->user->id)
        ->subject_id->toBe($this->user->id);
});

it('audits a failed sign-in against the account without storing the email', function (string $email, bool $knownAccount): void {
    Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => 'wrong-password-1'])
        ->call('authenticate');

    $entry = auditEntry('auth.login_failed');

    expect($entry->actor_id)->toBeNull()
        ->and($entry->subject_id)->toBe($knownAccount ? $this->user->id : null)
        ->and(json_encode($entry->new_values_redacted))->not->toContain($email);
})->with([
    'known account' => ['officer@muallaf.test', true],
    'unknown account' => ['nobody@muallaf.test', false],
]);

it('audits a rejected authenticator code', function (): void {
    $this->user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'officer@muallaf.test', 'password' => 'correct-horse-42'])
        ->call('authenticate')
        ->set('data.multiFactor.app.code', '000000')
        ->call('authenticate');

    $this->assertGuest();
    expect(auditEntry('auth.mfa_failed')->subject_id)->toBe($this->user->id)
        ->and(AuditLog::query()->where('action', 'auth.login')->exists())->toBeFalse();
});

it('audits role assignment and revocation in the role tenant', function (): void {
    seedRbac();
    $admin = grantRole(memberOf($this->tenant), 'center_admin', $this->tenant);
    $this->actingAs($admin);

    grantRole($this->user, 'dai', $this->tenant);
    app(RevokeRole::class)->handle($this->user->roleAssignments()->sole(), $admin, 'Moved office');

    expect(auditEntry('roles.revoked'))
        ->actor_id->toBe($admin->id)
        ->subject_id->toBe($this->user->id)
        ->tenant_id->toBe($this->tenant->id)
        ->old_values_redacted->toMatchArray(['role' => 'dai'])
        ->new_values_redacted->toBe(['reason' => 'Moved office']);

    expect(AuditLog::query()->where('action', 'roles.assigned')->where('subject_id', $this->user->id)->sole())
        ->new_values_redacted->toMatchArray(['role' => 'dai']);
});
