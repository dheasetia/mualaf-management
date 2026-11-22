<?php

declare(strict_types=1);

use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Prospects\Actions\CreateProspect;
use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Prospects\Pages\CreateProspect as CreateProspectPage;
use App\Filament\Center\Resources\Prospects\Pages\ViewProspect;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->staff = fn (string $role): User => withAuthenticator(grantRole(memberOf($this->dammam), $role, $this->dammam));

    $this->head = ($this->staff)('head_of_dawah');
    $this->daiUser = ($this->staff)('dai');

    [$this->dai, $this->otherDai, $this->mine, $this->theirs] = inTenant($this->dammam, function (): array {
        $dai = Dai::factory()->create(['user_id' => $this->daiUser->id]);
        $otherDai = Dai::factory()->create();
        $create = fn (string $name, int $daiId): Prospect => app(CreateProspect::class)->handle(
            new ProspectData($name, Gender::Male, ProspectSource::WalkIn, primaryDaiId: $daiId),
            $this->head,
        );

        return [$dai, $otherDai, $create('Mine Prospect', $dai->id), $create('Their Prospect', $otherDai->id)];
    });
});

it('gives each office role the prospect screens the matrix allows', function (string $role, int $list, int $create): void {
    $user = ($this->staff)($role);

    $this->actingAs($user)->get('/center/DMM/prospects')->assertStatus($list);
    $this->actingAs($user)->get('/center/DMM/prospects/create')->assertStatus($create);
})->with([
    'head of dawah' => ['head_of_dawah', 200, 200],
    'da\'i' => ['dai', 200, 200],
    'center admin (read)' => ['center_admin', 200, 403],
    'registration officer (read)' => ['registration_officer', 200, 403],
    'local auditor (read)' => ['local_auditor', 200, 403],
    'teacher' => ['teacher', 403, 403],
]);

it('shows a da\'i only the prospects they are assigned to', function (): void {
    $this->actingAs($this->daiUser)->get('/center/DMM/prospects')
        ->assertSee('Mine Prospect')
        ->assertDontSee('Their Prospect');

    $this->actingAs($this->daiUser)->get("/center/DMM/prospects/{$this->theirs->getRouteKey()}")->assertNotFound();
    $this->actingAs($this->head)->get("/center/DMM/prospects/{$this->theirs->getRouteKey()}")->assertOk();
});

it('lets the head of dawah pick the primary da\'i on the form; a da\'i is not asked', function (): void {
    inCenterPanel($this->dammam, $this->head);
    Livewire::test(CreateProspectPage::class)
        ->fillForm(['full_name' => 'Maria Cruz', 'gender' => 'female', 'source_type' => 'event', 'primary_dai_id' => $this->otherDai->id])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(inTenant($this->dammam, fn () => Prospect::query()->where('full_name', 'Maria Cruz')->sole()->primary_dai_id))->toBe($this->otherDai->id);

    inCenterPanel($this->dammam, $this->daiUser);
    Livewire::test(CreateProspectPage::class)->assertFormFieldHidden('primary_dai_id');
});

it('shows a possible duplicate on the phone field', function (): void {
    inCenterPanel($this->dammam, $this->head);
    $form = ['full_name' => 'Same Phone', 'gender' => 'male', 'source_type' => 'walk_in', 'primary_dai_id' => $this->dai->id, 'phone' => '0551112222'];

    Livewire::test(CreateProspectPage::class)->fillForm($form)->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateProspectPage::class)->fillForm($form)->call('create')->assertHasFormErrors(['phone']);
});

it('records a follow-up and changes status from the prospect page', function (): void {
    inCenterPanel($this->dammam, $this->daiUser);

    Livewire::test(ViewProspect::class, ['record' => $this->mine->getRouteKey()])
        ->assertActionHidden('assignDai')
        ->callAction('recordFollowUp', [
            'follow_up_type' => 'call',
            'contact_method' => 'whatsapp',
            'occurred_at' => now()->subHour()->toDateTimeString(),
            'summary' => 'Asked about prayer.',
            'confidentiality_level' => 'normal',
        ])
        ->assertHasNoActionErrors()
        ->callAction('changeStatus', ['status' => 'active_follow_up'])
        ->assertHasNoActionErrors();

    expect($this->mine->refresh()->status)->toBe(ProspectStatus::ActiveFollowUp);
});

it('lets the head of dawah reassign the primary da\'i from the prospect page', function (): void {
    inCenterPanel($this->dammam, $this->head);

    Livewire::test(ViewProspect::class, ['record' => $this->mine->getRouteKey()])
        ->callAction('assignDai', ['dai_id' => $this->otherDai->id, 'reason' => 'Workload'])
        ->assertHasNoActionErrors();

    expect($this->mine->refresh()->primary_dai_id)->toBe($this->otherDai->id);
});
