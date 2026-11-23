<?php

declare(strict_types=1);

use App\Domain\Platform\Models\Country;
use App\Domain\Reporting\Services\DailyStatistics;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
});

it('shows the office dashboard from the aggregate tables to reports.view_office only', function (): void {
    documentsConvert($this->tenant);
    app(DailyStatistics::class)->aggregate(CarbonImmutable::parse('2026-11-01'));
    $admin = withAuthenticator(grantRole(memberOf($this->tenant), 'center_admin', $this->tenant));

    $this->actingAs($admin)->get('/center/DMM/dashboard-report')->assertOk()
        ->assertSee(__('reports.metrics.shahadah_verified'))->assertSee('2026-11');
    $this->actingAs(grantRole(memberOf($this->tenant), 'dai', $this->tenant))->get('/center/DMM/dashboard-report')->assertForbidden();
});

it('shows the national dashboard to reports.view_national only', function (): void {
    $this->actingAs(withAuthenticator(grantRole(User::factory()->create(), 'central_reporting')))->get('/central/national-dashboard')->assertOk()
        ->assertSee(__('reports.labels.not_aggregated'));
    $this->actingAs(withAuthenticator(grantRole(User::factory()->create(), 'support_officer')))->get('/central/national-dashboard')->assertForbidden();
});
