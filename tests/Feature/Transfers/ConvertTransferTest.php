<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\DaiAssignment;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Actions\AcceptConvertTransfer;
use App\Domain\Transfers\Actions\CancelConvertTransfer;
use App\Domain\Transfers\Actions\RequestConvertTransfer;
use App\Domain\Transfers\Actions\ReviewConvertTransfer;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Events\TransferCompleted;
use App\Domain\Transfers\Models\ConvertTransferEvent;
use App\Domain\Transfers\Models\ConvertTransferHistory;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Support\Tenancy\TenantScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->iqamah = saudiId('233445566');
    extract(transferSetup($this->dammam, $this->riyadh, $this->iqamah));
    [$this->convert, $this->fromHead, $this->toHead, $this->fromDai, $this->toDai] = [$convert, $fromHead, $toHead, $fromDai, $toDai];

    $this->request = fn (): ConvertTransferRequest => inTenant($this->riyadh, fn () => app(RequestConvertTransfer::class)
        ->handle(new IdentifierData(IdentityType::Iqamah, $this->iqamah), 'Moved to Riyadh for work', $this->toHead));
    $this->approve = fn (ConvertTransferRequest $request) => inTenant($this->dammam, fn () => app(ReviewConvertTransfer::class)->approve($request, $this->fromHead));
    $this->accept = fn (ConvertTransferRequest $request, ?int $daiId = null) => inTenant($this->riyadh, fn () => app(AcceptConvertTransfer::class)
        ->handle($request, $daiId ?? $this->toDai->id, $this->toHead));
});

it('moves a convert to the new office: request, approval, acceptance', function (): void {
    Event::fake([TransferCompleted::class]);

    $request = ($this->request)();
    expect($request->status)->toBe(TransferStatus::Pending)
        ->and($request->from_tenant_id)->toBe($this->dammam->id);

    ($this->approve)($request);
    ($this->accept)($request);

    $convert = inTenant($this->riyadh, fn () => Convert::query()->findOrFail($this->convert->id));
    expect($convert)
        ->current_tenant_id->toBe($this->riyadh->id)
        ->origin_tenant_id->toBe($this->dammam->id)
        ->primary_dai_id->toBe($this->toDai->id)
        ->and(inTenant($this->dammam, fn () => Convert::query()->find($this->convert->id)))->toBeNull();

    $assignments = DaiAssignment::query()->withoutGlobalScope(TenantScope::class)->where('subject_type', 'convert')->where('subject_id', $this->convert->id)->get();
    expect($assignments->whereNull('ended_at')->sole()->dai_id)->toBe($this->toDai->id)
        ->and($assignments->firstWhere('dai_id', $this->fromDai->id)->end_reason)->toBe('transferred');

    inTenant($this->riyadh, function () use ($request): void {
        expect(ConvertTransferHistory::query()->sole())
            ->from_tenant_id->toBe($this->dammam->id)
            ->previous_primary_dai_id->toBe($this->fromDai->id)
            ->new_primary_dai_id->toBe($this->toDai->id)
            ->and(ConvertTransferEvent::query()->where('transfer_request_id', $request->id)->pluck('event_type')->all())
            ->toBe(['requested', 'approved', 'accepted', 'completed']);
    });

    expect(AuditLog::query()->where('action', 'like', 'transfer.%')->orderBy('id')->pluck('action')->all())
        ->toBe(['transfer.requested', 'transfer.approved', 'transfer.accepted', 'transfer.completed']);
    Event::assertDispatched(TransferCompleted::class);
});

it('refuses requests for converts of the own office, unknown identities and without permission', function (): void {
    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(RequestConvertTransfer::class)
        ->handle(new IdentifierData(IdentityType::Iqamah, $this->iqamah), 'x', $this->fromHead), 'identity_number'));

    inTenant($this->riyadh, fn () => expectRuleViolation(fn () => app(RequestConvertTransfer::class)
        ->handle(new IdentifierData(IdentityType::Iqamah, saudiId('211111111')), 'x', $this->toHead), 'identity_number'));

    expect(fn () => inTenant($this->riyadh, fn () => app(RequestConvertTransfer::class)
        ->handle(new IdentifierData(IdentityType::Iqamah, $this->iqamah), 'x', grantRole(memberOf($this->riyadh), 'registration_officer', $this->riyadh))))
        ->toThrow(AuthorizationException::class);
});

it('needs a reason to reject, and holds a new request for 30 days after a rejection', function (): void {
    $request = ($this->request)();

    inTenant($this->dammam, function () use ($request): void {
        expectRuleViolation(fn () => app(ReviewConvertTransfer::class)->reject($request, $this->fromHead, ''), 'note');
        app(ReviewConvertTransfer::class)->reject($request, $this->fromHead, 'Convert prefers to stay');
    });

    expectRuleViolation($this->request, 'identity_number');

    $this->travel(31)->days();
    expect(($this->request)()->status)->toBe(TransferStatus::Pending);
});

it('keeps each step with its own office', function (): void {
    $request = ($this->request)();

    inTenant($this->riyadh, fn () => expectRuleViolation(fn () => app(ReviewConvertTransfer::class)->approve($request, $this->toHead), 'status'));

    ($this->approve)($request);
    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(AcceptConvertTransfer::class)->handle($request, $this->fromDai->id, $this->fromHead), 'status'));
});

it('lets the requesting office cancel before accepting', function (): void {
    $request = ($this->request)();

    inTenant($this->riyadh, fn () => app(CancelConvertTransfer::class)->handle($request, $this->toHead, 'Wrong person'));

    expect(inTenant($this->riyadh, fn () => $request->refresh()->status))->toBe(TransferStatus::Cancelled)
        ->and(($this->request)()->status)->toBe(TransferStatus::Pending);
});

it('rolls back the whole completion when the new da\'i does not belong to the new office', function (): void {
    $request = ($this->request)();
    ($this->approve)($request);

    expectRuleViolation(fn () => ($this->accept)($request, $this->fromDai->id), 'dai_id');

    expect(inTenant($this->dammam, fn () => Convert::query()->find($this->convert->id)?->current_tenant_id))->toBe($this->dammam->id)
        ->and(inTenant($this->riyadh, fn () => $request->refresh()->status))->toBe(TransferStatus::Approved);
});

it('escalates requests left unanswered beyond the SLA; the old office can still decide', function (): void {
    $request = ($this->request)();
    $this->travel(8)->days();

    $this->artisan('transfers:escalate')->expectsOutputToContain('1 transfer(s) escalated')->assertSuccessful();

    expect(inTenant($this->riyadh, fn () => $request->refresh()->status))->toBe(TransferStatus::Escalated)
        ->and(AuditLog::query()->where('action', 'transfer.escalated')->exists())->toBeTrue();

    ($this->approve)($request);
    expect(inTenant($this->riyadh, fn () => $request->refresh()->status))->toBe(TransferStatus::Approved);
});
