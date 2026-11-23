<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Companies\Actions\PromoteCompany;
use App\Domain\Companies\Actions\SaveCompany;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Scopes\CompanyScope;
use App\Domain\Dai\Models\Dai;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Central\Resources\Companies\Pages\ManageCompanies as CentralCompanies;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->registrar = grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam);
    $this->centralAdmin = withAuthenticator(grantRole(User::factory()->create(), 'central_admin'));
    $this->national = Company::factory()->create(['name_ar' => 'شركة وطنية', 'registration_number' => '1010000001']);
    $this->save = fn (array $data, ?Company $company = null, ?Tenant $tenant = null) => inTenant($tenant ?? $this->dammam,
        fn () => app(SaveCompany::class)->handle($company, ['name_ar' => 'شركة الدمام', ...$data], $this->registrar));
});

it('keeps office companies to their office while every office sees the national directory', function (): void {
    $own = ($this->save)([]);

    expect($own->tenant_id)->toBe($this->dammam->id)
        ->and(inTenant($this->dammam, fn () => Company::query()->pluck('id')->sort()->values()->all()))->toBe([$this->national->id, $own->id])
        ->and(inTenant($this->riyadh, fn () => Company::query()->pluck('id')->all()))->toBe([$this->national->id])
        ->and(Company::query()->pluck('id')->all())->toBe([$this->national->id]);
})->group('tenant-isolation');

it('reads coordinates from a Google Maps link and refuses other links', function (): void {
    $company = ($this->save)(['google_maps_url' => 'https://www.google.com/maps/place/X/@26.4207,50.0888,15z']);

    expect((float) $company->latitude)->toBe(26.4207)
        ->and((float) $company->longitude)->toBe(50.0888);

    expectRuleViolation(fn () => ($this->save)(['google_maps_url' => 'https://maps.example.com/@26.4,50.0']), 'google_maps_url');
});

it('lets only the owner change a company', function (): void {
    expectRuleViolation(fn () => ($this->save)(['name_ar' => 'x'], $this->national), 'name_ar');

    $own = ($this->save)([]);
    expect(inTenant($this->dammam, fn () => $this->registrar->can('update', $own)))->toBeTrue()
        ->and(inTenant($this->dammam, fn () => $this->registrar->can('update', $this->national)))->toBeFalse();
});

it('promotes an office company, or merges it into the national one with the same registration number', function (): void {
    $unique = ($this->save)(['registration_number' => '2020000002']);
    $duplicate = ($this->save)(['registration_number' => '1010000001']);
    $prospect = inTenant($this->dammam, fn () => Prospect::factory()->create(['primary_dai_id' => Dai::factory()->create()->id, 'company_id' => $duplicate->id]));

    app(PromoteCompany::class)->handle($unique, $this->centralAdmin);
    $merged = app(PromoteCompany::class)->handle($duplicate, $this->centralAdmin);

    expect($unique->refresh()->tenant_id)->toBeNull()
        ->and($merged->id)->toBe($this->national->id)
        ->and(Company::query()->withoutGlobalScope(CompanyScope::class)->find($duplicate->id))->toBeNull()
        ->and(inTenant($this->dammam, fn () => $prospect->refresh()->company_id))->toBe($this->national->id)
        ->and(AuditLog::query()->whereIn('action', ['companies.promoted', 'companies.merged'])->count())->toBe(2);
});

it('lists office companies in Central with a promote action', function (): void {
    $own = ($this->save)([]);
    Filament::setCurrentPanel(Filament::getPanel('central'));

    Livewire::actingAs($this->centralAdmin)->test(CentralCompanies::class)
        ->assertCanSeeTableRecords([$this->national, $own])
        ->assertActionHidden(TestAction::make('promote')->table($this->national))
        ->callAction(TestAction::make('promote')->table($own))
        ->assertHasNoActionErrors();

    expect($own->refresh()->tenant_id)->toBeNull();
});
