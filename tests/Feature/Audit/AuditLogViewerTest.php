<?php

declare(strict_types=1);

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);

    $audit = app(AuditLogger::class);
    $this->dammamEntry = $audit->record('probe.dammam', tenantId: $this->dammam->id);
    $this->riyadhEntry = $audit->record('probe.riyadh', tenantId: $this->riyadh->id);
    $audit->record('probe.platform');
});

it('shows a central auditor the entries of every office and of the platform', function (): void {
    $this->actingAs(withAuthenticator(grantRole(User::factory()->create(), 'central_auditor')))
        ->get('/central/audit-logs')
        ->assertOk()
        ->assertSee(['probe.dammam', 'probe.riyadh', 'probe.platform']);
});

it('shows a local auditor only the entries of their own office', function (): void {
    $this->actingAs(withAuthenticator(grantRole(memberOf($this->dammam, $this->riyadh), 'local_auditor', $this->dammam)))
        ->get('/center/DMM/audit-logs')
        ->assertOk()
        ->assertSee('probe.dammam')
        ->assertDontSee(['probe.riyadh', 'probe.platform']);
})->group('tenant-isolation');

it('denies the audit log to office roles without audit permission', function (): void {
    $this->actingAs(grantRole(memberOf($this->dammam), 'dai', $this->dammam))
        ->get('/center/DMM/audit-logs')
        ->assertForbidden();
});

it('does not let an office auditor open an entry of another office', function (): void {
    $auditor = grantRole(memberOf($this->dammam, $this->riyadh), 'local_auditor', $this->dammam);

    inTenant($this->dammam, function () use ($auditor): void {
        expect($auditor->can('view', $this->dammamEntry))->toBeTrue()
            ->and($auditor->can('view', $this->riyadhEntry))->toBeFalse()
            ->and($auditor->can('update', $this->dammamEntry))->toBeFalse()
            ->and($auditor->can('delete', $this->dammamEntry))->toBeFalse();
    });
})->group('tenant-isolation');
