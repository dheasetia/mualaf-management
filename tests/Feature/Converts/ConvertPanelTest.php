<?php

declare(strict_types=1);

use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Converts\Pages\EditConvert;
use App\Filament\Center\Resources\Converts\Pages\ViewConvert;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->staff = fn (string $role): User => withAuthenticator(grantRole(memberOf($this->dammam), $role, $this->dammam));
    [$this->normal, $this->secret] = inTenant($this->dammam, fn (): array => [
        Convert::factory()->create(['full_name' => 'Normal Person']),
        Convert::factory()->create(['full_name' => 'Secret Person', 'disclosure_risk_level' => DisclosureLevel::HighConfidentiality]),
    ]);
});

it('opens the convert list to roles with converts.view, view_own or view_class', function (string $role, int $status): void {
    $this->actingAs(($this->staff)($role))->get('/center/DMM/converts')->assertStatus($status);
})->with([
    'center admin' => ['center_admin', 200],
    'registration officer' => ['registration_officer', 200],
    'da\'i' => ['dai', 200],
    'teacher (own classes only)' => ['teacher', 200],
    'pilgrimage officer' => ['pilgrimage_officer', 200],
]);

it('lists high-confidentiality converts under an alias that cannot be opened', function (): void {
    $registrar = ($this->staff)('registration_officer');

    $this->actingAs($registrar)->get('/center/DMM/converts')
        ->assertSee('Normal Person')
        ->assertDontSee('Secret Person')
        ->assertSee($this->secret->alias());

    $this->actingAs($registrar)->get("/center/DMM/converts/{$this->secret->getRouteKey()}")->assertForbidden();
    $this->actingAs(($this->staff)('head_of_dawah'))->get("/center/DMM/converts/{$this->secret->getRouteKey()}")
        ->assertOk()->assertSee('Secret Person');
});

it('offers disclosure and status changes to the head of dawah only', function (): void {
    inCenterPanel($this->dammam, ($this->staff)('registration_officer'));
    Livewire::test(ViewConvert::class, ['record' => $this->normal->getRouteKey()])
        ->assertActionHidden('changeDisclosure')
        ->assertActionHidden('changeStatus');

    inCenterPanel($this->dammam, ($this->staff)('head_of_dawah'));
    Livewire::test(ViewConvert::class, ['record' => $this->normal->getRouteKey()])
        ->callAction('changeDisclosure', ['level' => 'restricted', 'reason' => 'Requested by convert'])
        ->assertHasNoActionErrors();

    expect($this->normal->refresh()->disclosure_risk_level)->toBe(DisclosureLevel::Restricted);
});

it('hides religious background from the edit form without converts.view_sensitive', function (): void {
    inCenterPanel($this->dammam, ($this->staff)('registration_officer'));
    Livewire::test(EditConvert::class, ['record' => $this->normal->getRouteKey()])
        ->assertFormFieldHidden('previous_religion_id')
        ->fillForm(['islamic_name' => 'Yusuf'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->normal->refresh()->islamic_name)->toBe('Yusuf');
});
