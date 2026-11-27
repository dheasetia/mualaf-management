<?php

declare(strict_types=1);

use App\Domain\Reporting\Enums\Metric;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Central\Widgets\NationalQueuesWidget;
use App\Filament\Central\Widgets\NationalStatsWidget;
use App\Filament\Central\Widgets\OfficeOverviewWidget;
use App\Filament\Central\Widgets\OfficeShahadahChart;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Filament::setCurrentPanel(Filament::getPanel('central'));
});

function centralUser(string $role): User
{
    $user = grantRole(User::factory()->create(), $role);
    test()->actingAs($user);

    return $user;
}

/** @param  list<array{0: Tenant, 1: Metric, 2: CarbonImmutable, 3: int}>  $rows */
function tenantStats(array $rows): void
{
    DB::table('report_daily_tenant_stats')->insert(array_map(fn (array $row): array => [
        'tenant_id' => $row[0]->id, 'metric' => $row[1]->value, 'stat_date' => $row[2]->toDateString(), 'value' => $row[3],
    ], $rows));
}

it('shows national figures only to holders of reports.view_national', function (): void {
    centralUser('support_officer');
    expect([NationalStatsWidget::canView(), OfficeOverviewWidget::canView(), OfficeShahadahChart::canView(), NationalQueuesWidget::canView()])->each->toBeFalse();

    centralUser('central_reporting');
    expect([NationalStatsWidget::canView(), OfficeOverviewWidget::canView(), OfficeShahadahChart::canView()])->each->toBeTrue()
        ->and(NationalQueuesWidget::canView())->toBeFalse();
    Livewire::test(NationalStatsWidget::class)->assertSuccessful()->assertSee(__('dashboard.national.offices_active'));
    Livewire::test(OfficeShahadahChart::class)->assertSuccessful();
});

it('shows each queue only to those who may decide it', function (): void {
    centralUser('central_admin');
    $widget = Livewire::test(NationalQueuesWidget::class)->instance();
    $labels = array_map(fn ($stat): string => (string) $stat->getLabel(), (fn (): array => $this->getStats())->call($widget));
    expect($labels)->toBe([__('dashboard.national.void_requests')]);

    centralUser('central_auditor');
    $widget = Livewire::test(NationalQueuesWidget::class)->instance();
    $labels = array_map(fn ($stat): string => (string) $stat->getLabel(), (fn (): array => $this->getStats())->call($widget));
    expect($labels)->toBe([__('dashboard.national.privileged_requests')]);
});

it('compares active centers and marks quiet ones', function (): void {
    $today = CarbonImmutable::now((string) config('app.display_timezone'));
    $busy = Tenant::factory()->active()->create(['name_ar' => 'مركز نشط', 'name_en' => 'Busy center']);
    $quiet = Tenant::factory()->active()->create(['name_ar' => 'مركز هادئ', 'name_en' => 'Quiet center']);
    $suspended = Tenant::factory()->suspended()->create(['name_ar' => 'مركز موقوف', 'name_en' => 'Suspended center']);
    tenantStats([
        [$busy, Metric::ShahadahVerified, $today, 3],
        [$busy, Metric::ConvertsActive, $today->subDay(), 10],
        [$busy, Metric::ConvertsActive, $today, 12],
        [$quiet, Metric::ProspectsCreated, $today->subDays(30), 2],
        [$quiet, Metric::FollowUpsOverdue, $today, 4],
    ]);

    centralUser('central_reporting');
    $component = Livewire::test(OfficeOverviewWidget::class)
        ->assertCanSeeTableRecords([$busy, $quiet])
        ->assertCanNotSeeTableRecords([$suspended])
        ->assertTableColumnStateSet('shahadah_year', 3, $busy)
        ->assertTableColumnStateSet('converts_active', 12, $busy)
        ->assertTableColumnStateSet('follow_ups_overdue', 4, $quiet)
        ->assertTableColumnStateSet('last_activity', $today->toDateString(), $busy)
        ->assertTableColumnStateSet('last_activity', $today->subDays(30)->toDateString(), $quiet);

    $column = fn (Tenant $record) => $component->instance()->getTable()->getColumn('last_activity')->record($record);
    expect($column($busy)->getColor($column($busy)->getState()))->toBe('success')
        ->and($column($quiet)->getColor($column($quiet)->getState()))->toBe('danger');
});
