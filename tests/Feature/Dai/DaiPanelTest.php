<?php

declare(strict_types=1);

use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Domain\Platform\Models\Language;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Pages\DaiWorkload;
use App\Filament\Center\Resources\Dais\Pages\CreateDai;
use App\Filament\Center\Resources\Dais\Pages\EditDai;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->staff = fn (string $role) => withAuthenticator(grantRole(memberOf($this->dammam), $role, $this->dammam));
});

it('gives each office role the da\'i screens the matrix allows', function (string $role, int $list, int $create, int $workload): void {
    $user = ($this->staff)($role);

    $this->actingAs($user)->get('/center/DMM/dais')->assertStatus($list);
    $this->actingAs($user)->get('/center/DMM/dais/create')->assertStatus($create);
    $this->actingAs($user)->get('/center/DMM/dai-workload')->assertStatus($workload);
})->with([
    'center admin' => ['center_admin', 200, 200, 403],
    'head of dawah' => ['head_of_dawah', 200, 403, 200],
    'local auditor' => ['local_auditor', 200, 403, 403],
    'da\'i' => ['dai', 403, 403, 403],
]);

it('lists only the da\'i of the office in context', function (): void {
    inTenant($this->dammam, fn () => Dai::factory()->create(['full_name' => 'Dammam Dai']));
    inTenant(Tenant::factory()->active()->create(['code' => 'RUH']), fn () => Dai::factory()->create(['full_name' => 'Riyadh Dai']));

    $this->actingAs(($this->staff)('center_admin'))->get('/center/DMM/dais')
        ->assertSee('Dammam Dai')
        ->assertDontSee('Riyadh Dai');
})->group('tenant-isolation');

it('registers a da\'i with languages from the form', function (): void {
    $user = inCenterPanel($this->dammam, ($this->staff)('center_admin'));
    $tagalog = Language::factory()->create();

    Livewire::actingAs($user)->test(CreateDai::class)
        ->fillForm([
            'employee_number' => 'D-200',
            'full_name' => 'Yusuf Reyes',
            'gender' => 'male',
            'employment_status' => 'volunteer',
            'specialties' => ['Comparative religion'],
            'language_rows' => [['language_id' => $tagalog->id, 'proficiency' => 'native']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $dai = Dai::query()->where('employee_number', 'D-200')->sole();
    expect($dai->specialties)->toBe(['Comparative religion'])
        ->and($dai->languages()->sole()->id)->toBe($tagalog->id);
});

it('shows a broken supervisor rule on the supervisor field', function (): void {
    $user = inCenterPanel($this->dammam, ($this->staff)('center_admin'));
    $head = Dai::factory()->create();
    Dai::factory()->create(['supervisor_dai_id' => $head->id, 'full_name' => 'Junior']);
    $junior = Dai::query()->where('full_name', 'Junior')->sole();

    Livewire::actingAs($user)->test(EditDai::class, ['record' => $head->getRouteKey()])
        ->fillForm(['supervisor_dai_id' => $junior->id])
        ->call('save')
        ->assertHasFormErrors(['supervisor_dai_id']);
});

it('changes status from the edit page', function (): void {
    $user = inCenterPanel($this->dammam, ($this->staff)('center_admin'));
    $dai = Dai::factory()->create();

    Livewire::actingAs($user)->test(EditDai::class, ['record' => $dai->getRouteKey()])
        ->callAction('changeStatus', ['status' => 'on_leave', 'reason' => 'Umrah'])
        ->assertHasNoActionErrors();

    expect($dai->refresh()->status)->toBe(DaiStatus::OnLeave);
});

it('counts active assignments and supervisees per da\'i', function (): void {
    $user = inCenterPanel($this->dammam, ($this->staff)('head_of_dawah'));
    $dai = Dai::factory()->create();
    DaiAssignment::factory()->for($dai)->count(2)->create();
    DaiAssignment::factory()->for($dai)->create(['subject_type' => 'convert']);
    DaiAssignment::factory()->for($dai)->secondary()->create();
    DaiAssignment::factory()->for($dai)->ended()->create();
    Dai::factory()->create(['supervisor_dai_id' => $dai->id]);
    Dai::factory()->create(['status' => DaiStatus::Inactive]);

    Livewire::actingAs($user)->test(DaiWorkload::class)
        ->assertCanSeeTableRecords([$dai])
        ->assertTableColumnStateSet('primary_prospects_count', 2, $dai)
        ->assertTableColumnStateSet('primary_converts_count', 1, $dai)
        ->assertTableColumnStateSet('other_assignments_count', 1, $dai)
        ->assertTableColumnStateSet('supervisees_count', 1, $dai)
        ->assertCountTableRecords(2);
});
