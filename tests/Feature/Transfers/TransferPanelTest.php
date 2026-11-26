<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Actions\ReviewConvertTransfer;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Filament\Center\Pages\NationalIdentitySearch;
use App\Filament\Center\Resources\ConvertTransferRequests\Pages\ManageConvertTransferRequests;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->iqamah = saudiId('277889900');
    ['convert' => $this->convert, 'fromHead' => $this->fromHead, 'toHead' => $this->toHead, 'toDai' => $this->toDai] = transferSetup($this->dammam, $this->riyadh, $this->iqamah);
});

it('requests a transfer from the national search result, then accepts it from the transfers list', function (): void {
    inCenterPanel($this->riyadh, $this->toHead);

    Livewire::test(NationalIdentitySearch::class)
        ->fillForm(['identity_type' => 'iqamah', 'identity_number' => $this->iqamah])
        ->call('search')
        ->assertSet('data.identity_number', null)
        ->call('requestTransfer', 'Moved to Riyadh');

    $request = inTenant($this->riyadh, fn () => ConvertTransferRequest::query()->sole());
    expect($request->status)->toBe(TransferStatus::Pending);

    inTenant($this->dammam, fn () => app(ReviewConvertTransfer::class)->approve($request, $this->fromHead));

    inCenterPanel($this->riyadh, $this->toHead);
    Livewire::test(ManageConvertTransferRequests::class)
        ->assertCanSeeTableRecords([$request])
        ->assertActionHidden(TestAction::make('approve')->table($request))
        ->callAction(TestAction::make('accept')->table($request), ['dai_id' => $this->toDai->id])
        ->assertHasNoActionErrors();

    expect(inTenant($this->riyadh, fn () => Convert::query()->find($this->convert->id)))->not->toBeNull();
});

it('opens the transfers list to roles holding a transfer permission', function (string $role, int $status): void {
    $this->actingAs(grantRole(memberOf($this->dammam), $role, $this->dammam))
        ->get('/center/DMM/convert-transfer-requests')
        ->assertStatus($status);
})->with([
    'head of dawah' => ['head_of_dawah', 200],
    'registration officer' => ['registration_officer', 403],
]);
