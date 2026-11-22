<?php

declare(strict_types=1);

use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Prospects\Actions\ChangeProspectStatus;
use App\Domain\Prospects\Actions\CreateProspect;
use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Actions\ProspectTransfers;
use App\Domain\Transfers\Enums\ProspectTransferStatus;
use App\Support\Tenancy\TenantScope;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->dammamHead = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    $this->riyadhHead = grantRole(memberOf($this->riyadh), 'head_of_dawah', $this->riyadh);
    $this->riyadhDai = inTenant($this->riyadh, fn () => Dai::factory()->create());

    $this->prospect = inTenant($this->dammam, function (): Prospect {
        $prospect = app(CreateProspect::class)->handle(new ProspectData('Ravi Kumar', Gender::Male, ProspectSource::Event, primaryDaiId: Dai::factory()->create()->id), $this->dammamHead);
        $prospect->forceFill(['status' => ProspectStatus::LearningIslam])->save();

        return $prospect;
    });
    $this->send = fn () => inTenant($this->dammam, fn () => app(ProspectTransfers::class)->request($this->prospect->id, $this->riyadh->id, 'Moved to Riyadh', $this->dammamHead));
});

it('moves a prospect with its status and history once the new office accepts', function (): void {
    $request = ($this->send)();

    inTenant($this->riyadh, fn () => app(ProspectTransfers::class)->approve($request, $this->riyadhDai->id, $this->riyadhHead));

    inTenant($this->riyadh, function () use ($request): void {
        $prospect = Prospect::query()->findOrFail($this->prospect->id);

        expect($request->refresh()->status)->toBe(ProspectTransferStatus::Completed)
            ->and($prospect->status)->toBe(ProspectStatus::LearningIslam)
            ->and($prospect->primary_dai_id)->toBe($this->riyadhDai->id)
            ->and($prospect->statusHistory()->latest('id')->first()->reason)->toBe('Moved to Riyadh');
    });

    expect(inTenant($this->dammam, fn () => Prospect::query()->find($this->prospect->id)))->toBeNull()
        ->and(DaiAssignment::query()->withoutGlobalScope(TenantScope::class)->active()->where('subject_id', $this->prospect->id)->sole()->dai_id)->toBe($this->riyadhDai->id);
})->group('tenant-isolation');

it('rejects with a reason, cancels from the sending office, and keeps one open request', function (): void {
    $request = ($this->send)();
    expectRuleViolation($this->send, 'identity_number');

    inTenant($this->riyadh, function () use ($request): void {
        expectRuleViolation(fn () => app(ProspectTransfers::class)->reject($request, $this->riyadhHead, null), 'note');
        app(ProspectTransfers::class)->reject($request, $this->riyadhHead, 'No da\'i for this language');
    });

    $second = ($this->send)();
    inTenant($this->dammam, fn () => app(ProspectTransfers::class)->cancel($second, $this->dammamHead));

    expect(inTenant($this->dammam, fn () => $second->refresh()->status))->toBe(ProspectTransferStatus::Cancelled);
});

it('does not send closed prospects or send to the same office', function (): void {
    inTenant($this->dammam, fn () => app(ChangeProspectStatus::class)->handle($this->prospect, ProspectStatus::LostContact, $this->dammamHead, 'No answer'));
    inTenant($this->dammam, fn () => app(ChangeProspectStatus::class)->handle($this->prospect->refresh(), ProspectStatus::Closed, $this->dammamHead, 'Closed'));

    expectRuleViolation($this->send, 'status');
    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(ProspectTransfers::class)->request($this->prospect->id, $this->dammam->id, 'x', $this->dammamHead), 'to_tenant_id'));
});
