<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Platform\Models\Country;
use App\Domain\Privileged\Actions\PrivilegedAccess;
use App\Domain\Privileged\Enums\PrivilegedStatus;
use App\Domain\Privileged\Models\PrivilegedAccessRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Central\Resources\PrivilegedAccess\Pages\ListPrivilegedAccess;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->convert = documentsConvert($this->tenant);
    $this->requester = withAuthenticator(grantRole(User::factory()->create(), 'central_admin'));
    $this->auditor = withAuthenticator(grantRole(User::factory()->create(), 'central_auditor'));
    $this->superAdmin = withAuthenticator(grantRole(User::factory()->create(), 'super_admin'));
    $this->access = fn (): PrivilegedAccess => app(PrivilegedAccess::class);
    $this->ask = fn (array $scopes = ['profile', 'identity'], int $minutes = 60, ?User $actor = null) => ($this->access)()->request(
        new IdentifierData(IdentityType::Iqamah, saudiId('277001122')), 'Ticket #42: duplicate record complaint', $scopes, $minutes, $actor ?? $this->requester,
    );
});

it('opens one convert for a limited time after someone else approves, auditing every view', function (): void {
    $request = ($this->ask)();

    expect($request)->status->toBe(PrivilegedStatus::Pending)->tenant_id->toBe($this->tenant->id)->resource_id->toBe($this->convert->id)
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->auditor->id)->count())->toBe(1)
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->requester->id)->count())->toBe(0);
    expect(fn () => ($this->access)()->open($request, $this->requester))->toThrow(RuleViolation::class);

    $grant = ($this->access)()->approve($request, $this->auditor);
    expect($grant)->status->toBe(PrivilegedStatus::Active)
        ->and($grant->expires_at->diffInMinutes(now()->addHour()))->toBeLessThan(1);

    $sections = ($this->access)()->open($grant, $this->requester);
    expect(array_keys($sections))->toBe(['profile', 'identity'])
        ->and($sections['profile']['full_name'])->toBe('Daniel Mendoza')
        ->and(array_values($sections['identity']))->toBe([saudiId('277001122')]);

    $viewed = AuditLog::query()->where('action', 'privileged.viewed')->sole();
    expect($viewed)->privileged_grant_id->toBe($grant->id)->tenant_id->toBe($this->tenant->id)->actor_id->toBe($this->requester->id);
    expect(fn () => ($this->access)()->open($grant, $this->auditor))->toThrow(AuthorizationException::class);
});

it('never lets a requester approve their own request, and rejecting needs a note', function (): void {
    $own = ($this->ask)(actor: $this->superAdmin);

    expectRuleViolation(fn () => ($this->access)()->approve($own, $this->superAdmin), 'status');
    expectRuleViolation(fn () => ($this->access)()->reject($own, $this->auditor, ''), 'reason');
    expect(fn () => ($this->access)()->approve(($this->ask)(), $this->requester))->toThrow(AuthorizationException::class);

    ($this->access)()->reject($own, $this->auditor, 'Use the office channel');
    expect($own->refresh()->status)->toBe(PrivilegedStatus::Rejected);
    expectRuleViolation(fn () => ($this->access)()->approve($own, $this->auditor), 'status');
});

it('checks the reason, scopes, duration and identity, and stays out of office panels', function (): void {
    expectRuleViolation(fn () => ($this->ask)([]), 'requested_scope');
    expectRuleViolation(fn () => ($this->ask)(['profile'], 1441), 'requested_duration_minutes');
    expectRuleViolation(fn () => ($this->ask)(['profile'], 5), 'requested_duration_minutes');
    expectRuleViolation(fn () => ($this->access)()->request(new IdentifierData(IdentityType::Iqamah, saudiId('277009999')), 'x', ['profile'], 60, $this->requester), 'identity_number');
    expect(fn () => ($this->ask)(actor: grantRole(User::factory()->create(), 'central_auditor')))->toThrow(AuthorizationException::class);

    inTenant($this->tenant, fn () => expectRuleViolation(fn () => ($this->ask)(), 'status'));
});

it('ends grants when revoked or expired', function (): void {
    $revoked = ($this->access)()->approve(($this->ask)(), $this->auditor);
    ($this->access)()->revoke($revoked, $this->requester);
    expect($revoked->refresh()->status)->toBe(PrivilegedStatus::Revoked);
    expectRuleViolation(fn () => ($this->access)()->open($revoked, $this->requester), 'status');

    $short = ($this->access)()->approve(($this->ask)(['profile'], 15), $this->auditor);
    $this->travel(16)->minutes();
    $this->artisan('privileged:expire')->assertSuccessful();

    expect($short->refresh()->status)->toBe(PrivilegedStatus::Expired)
        ->and(AuditLog::query()->where('action', 'privileged.expired')->sole()->privileged_grant_id)->toBe($short->id);
});

it('runs from the Central panel and shows the grant only to its requester', function (): void {
    $this->actingAs($this->requester);
    Filament::setCurrentPanel(Filament::getPanel('central'));

    Livewire::test(ListPrivilegedAccess::class)
        ->callAction(TestAction::make('request')->table(), [
            'identity_type' => 'iqamah', 'identity_number' => saudiId('277001122'), 'reason' => 'Support ticket', 'scope' => ['profile'], 'minutes' => 30,
        ])
        ->assertHasNoActionErrors();

    $grant = ($this->access)()->approve(PrivilegedAccessRequest::query()->sole(), $this->auditor);

    $this->actingAs($this->requester)->get('/central/privileged-access/'.$grant->getRouteKey())->assertOk()
        ->assertSee(__('privileged.view_notice'))->assertSee('Daniel Mendoza');
    $this->actingAs($this->auditor)->get('/central/privileged-access/'.$grant->getRouteKey())->assertForbidden();
});
