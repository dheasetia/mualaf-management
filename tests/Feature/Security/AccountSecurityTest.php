<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Notifications\NewDeviceLoginNotification;
use App\Domain\Identity\Services\AccessReview;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedRbac();
    Notification::fake();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->login = function (User $user, string $browser): void {
        $this->withHeader('User-Agent', $browser);
        app('request')->headers->set('User-Agent', $browser);
        event(new Login('web', $user, false));
    };
});

it('emails the owner when an account signs in from a new browser, but not on the first sign-in', function (): void {
    $user = memberOf($this->tenant);

    ($this->login)($user, 'Firefox/130 Linux');
    ($this->login)($user, 'Firefox/130 Linux');
    Notification::assertNothingSentTo($user);

    ($this->login)($user, 'Safari/18 iPhone');
    Notification::assertSentTo($user, NewDeviceLoginNotification::class);
    expect(AuditLog::query()->where('action', 'auth.new_device')->count())->toBe(1);
});

it('reports again for a browser not seen for a long time', function (): void {
    $user = memberOf($this->tenant);
    ($this->login)($user, 'Firefox/130 Linux');
    ($this->login)($user, 'Chrome/131 Windows');
    $this->travel(100)->days();

    ($this->login)($user, 'Firefox/130 Linux');

    Notification::assertSentToTimes($user, NewDeviceLoginNotification::class, 2);
});

it('flags privileged accounts without an authenticator and unused accounts in the access review', function (): void {
    $approver = grantRole(memberOf($this->tenant), 'approver', $this->tenant);
    $dai = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    $secured = withAuthenticator(grantRole(memberOf($this->tenant), 'approver', $this->tenant));
    User::query()->whereKey([$approver->id, $dai->id, $secured->id])->update(['last_login_at' => now()]);
    $this->travel(120)->days();
    User::query()->whereKey([$approver->id, $secured->id])->update(['last_login_at' => now()]);

    $rows = collect(app(AccessReview::class)->rows($this->tenant->id))->keyBy('user_id');

    expect($rows[$approver->id])->missing_mfa->toBeTrue()->stale->toBeFalse()
        ->and($rows[$dai->id])->missing_mfa->toBeFalse()->stale->toBeTrue()
        ->and($rows[$secured->id])->missing_mfa->toBeFalse()->stale->toBeFalse();

    $this->artisan('access:review')->assertSuccessful();
    expect(AuditLog::query()->where('action', 'access.reviewed')->where('tenant_id', $this->tenant->id)->sole()->new_values_redacted)
        ->toMatchArray(['accounts' => 3, 'missing_mfa' => 1, 'stale' => 1]);
});

it('opens the access review page to audit.view_office only', function (): void {
    $auditor = grantRole(memberOf($this->tenant), 'local_auditor', $this->tenant);

    $this->actingAs($auditor)->get('/center/DMM/access-review')->assertOk()->assertSee(__('identity.access_review.title'));
    $this->actingAs(grantRole(memberOf($this->tenant), 'dai', $this->tenant))->get('/center/DMM/access-review')->assertForbidden();
});
