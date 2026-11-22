<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Dai\Data\FollowUpData;
use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Enums\FollowUpType;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Domain\Dai\Models\DaiFollowUp;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Prospects\Actions\AssignProspectDai;
use App\Domain\Prospects\Actions\ChangeProspectStatus;
use App\Domain\Prospects\Actions\CreateProspect;
use App\Domain\Prospects\Actions\RecordProspectFollowUp;
use App\Domain\Prospects\Actions\UpdateProspect;
use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    seedRbac();
    [$this->dammam, $this->riyadh] = Tenant::factory()->active()->count(2)->create();
    app(TenantContext::class)->set($this->dammam);

    $this->head = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    $this->daiUser = grantRole(memberOf($this->dammam), 'dai', $this->dammam);
    $this->dai = Dai::factory()->create(['user_id' => $this->daiUser->id]);
    $this->otherDai = Dai::factory()->create();
});

function prospectData(array $overrides = []): ProspectData
{
    return new ProspectData(...[
        'fullName' => 'John Santos',
        'gender' => Gender::Male,
        'source' => ProspectSource::DaiContact,
        ...$overrides,
    ]);
}

function followUp(array $overrides = []): FollowUpData
{
    return new FollowUpData(...[
        'type' => FollowUpType::Visit,
        'contactMethod' => ContactMethod::InPerson,
        'occurredAt' => CarbonImmutable::now()->subHour(),
        'summary' => 'Talked about tawheed.',
        ...$overrides,
    ]);
}

function expectRuleViolation(Closure $action, string $field): void
{
    expect($action)->toThrow(fn (RuleViolation $violation) => expect($violation->field)->toBe($field));
}

it('registers a prospect with its first status, primary assignment and audit', function (): void {
    $prospect = app(CreateProspect::class)->handle(prospectData([
        'fullName' => 'Jöhn  SANTOS',
        'phone' => '0501234567',
        'primaryDaiId' => $this->otherDai->id,
    ]), $this->head);

    expect($prospect->refresh())
        ->status->toBe(ProspectStatus::New)
        ->primary_dai_id->toBe($this->otherDai->id)
        ->name_search_key->toBe('john santos')
        ->and($prospect->statusHistory()->sole()->to_status)->toBe(ProspectStatus::New)
        ->and(DaiAssignment::query()->active()->where('subject_id', $prospect->id)->sole()->dai_id)->toBe($this->otherDai->id)
        ->and(DB::table('prospects')->where('id', $prospect->id)->value('phone_encrypted'))->not->toContain('501234567')
        ->and(AuditLog::query()->whereIn('action', ['prospects.created', 'dai.primary_assigned'])->count())->toBe(2);
});

it('makes a da\'i the primary da\'i of the prospects they register', function (): void {
    $prospect = app(CreateProspect::class)->handle(prospectData(['primaryDaiId' => $this->otherDai->id]), $this->daiUser);

    expect($prospect->primary_dai_id)->toBe($this->dai->id);

    $unlinked = grantRole(memberOf($this->dammam), 'dai', $this->dammam);
    expectRuleViolation(fn () => app(CreateProspect::class)->handle(prospectData(), $unlinked), 'primary_dai_id');
});

it('flags a phone already used by a prospect of the same office, however it is typed', function (): void {
    app(CreateProspect::class)->handle(prospectData(['phone' => '+966 50 123 4567', 'primaryDaiId' => $this->dai->id]), $this->head);

    expectRuleViolation(fn () => app(CreateProspect::class)->handle(prospectData(['phone' => '٠٥٠١٢٣٤٥٦٧', 'primaryDaiId' => $this->dai->id]), $this->head), 'phone');

    $confirmed = app(CreateProspect::class)->handle(prospectData(['phone' => '0501234567', 'primaryDaiId' => $this->dai->id, 'confirmedPossibleDuplicate' => true]), $this->head);
    expect($confirmed->exists)->toBeTrue();

    $riyadhHead = grantRole(memberOf($this->riyadh), 'head_of_dawah', $this->riyadh);
    inTenant($this->riyadh, function () use ($riyadhHead): void {
        $riyadhDai = Dai::factory()->create();
        expect(app(CreateProspect::class)->handle(prospectData(['phone' => '0501234567', 'primaryDaiId' => $riyadhDai->id]), $riyadhHead)->exists)->toBeTrue();
    });
});

it('changes status along the state machine only, with history', function (): void {
    $prospect = Prospect::factory()->status(ProspectStatus::Contacted)->create(['primary_dai_id' => $this->dai->id]);

    app(ChangeProspectStatus::class)->handle($prospect, ProspectStatus::ActiveFollowUp, $this->head);

    expect($prospect->refresh()->status)->toBe(ProspectStatus::ActiveFollowUp)
        ->and($prospect->statusHistory()->sole())
        ->from_status->toBe(ProspectStatus::Contacted)
        ->to_status->toBe(ProspectStatus::ActiveFollowUp);

    expectRuleViolation(fn () => app(ChangeProspectStatus::class)->handle($prospect, ProspectStatus::ReadyForShahadah, $this->head), 'status');
});

it('never converts or transfers through a plain status change', function (ProspectStatus $from, ProspectStatus $to): void {
    $prospect = Prospect::factory()->status($from)->create(['primary_dai_id' => $this->dai->id]);

    expectRuleViolation(fn () => app(ChangeProspectStatus::class)->handle($prospect, $to, $this->head), 'status');
})->with([
    'converted' => [ProspectStatus::ReadyForShahadah, ProspectStatus::Converted],
    'transferred' => [ProspectStatus::ActiveFollowUp, ProspectStatus::Transferred],
]);

it('requires a reason to close, and keeps a closed prospect read-only', function (): void {
    $prospect = Prospect::factory()->create(['primary_dai_id' => $this->dai->id]);

    expectRuleViolation(fn () => app(ChangeProspectStatus::class)->handle($prospect, ProspectStatus::Closed, $this->head), 'reason');

    app(ChangeProspectStatus::class)->handle($prospect, ProspectStatus::Closed, $this->head, 'Moved abroad');

    expectRuleViolation(fn () => app(UpdateProspect::class)->handle($prospect->refresh(), prospectData(), $this->head), 'status');
    expectRuleViolation(fn () => app(RecordProspectFollowUp::class)->handle($prospect, followUp(), $this->head), 'status');
});

it('hands the prospect to another active da\'i of the office, one primary at a time', function (): void {
    $prospect = app(CreateProspect::class)->handle(prospectData(['primaryDaiId' => $this->dai->id]), $this->head);

    app(AssignProspectDai::class)->handle($prospect, $this->otherDai->id, $this->head, 'Speaks Tagalog');

    expect($prospect->refresh()->primary_dai_id)->toBe($this->otherDai->id)
        ->and(DaiAssignment::query()->where('subject_id', $prospect->id)->count())->toBe(2)
        ->and(DaiAssignment::query()->active()->where('subject_id', $prospect->id)->sole()->dai_id)->toBe($this->otherDai->id)
        ->and(AuditLog::query()->where('action', 'dai.primary_assigned')->latest('id')->first()->old_values_redacted)->toBe(['dai_id' => $this->dai->id]);

    $onLeave = Dai::factory()->create(['status' => DaiStatus::OnLeave]);
    $otherOffice = inTenant($this->riyadh, fn () => Dai::factory()->create());

    foreach ([$onLeave->id, $otherOffice->id] as $daiId) {
        expectRuleViolation(fn () => app(AssignProspectDai::class)->handle($prospect, $daiId, $this->head), 'dai_id');
    }
});

it('records a follow-up, updates contact dates and moves a new prospect to contacted', function (): void {
    $prospect = app(CreateProspect::class)->handle(prospectData(), $this->daiUser);
    $next = CarbonImmutable::now()->addWeek()->startOfMinute();

    app(RecordProspectFollowUp::class)->handle($prospect, followUp(['nextFollowUpAt' => $next]), $this->daiUser);
    app(RecordProspectFollowUp::class)->handle($prospect, followUp(['occurredAt' => CarbonImmutable::now()->subWeek()]), $this->daiUser);

    $prospect->refresh();
    expect($prospect->status)->toBe(ProspectStatus::Contacted)
        ->and($prospect->first_contact_at->lessThan($prospect->last_contact_at))->toBeTrue()
        ->and($prospect->next_follow_up_at->equalTo($next))->toBeTrue()
        ->and(DaiFollowUp::query()->where('dai_id', $this->dai->id)->count())->toBe(2);
});

it('lets a da\'i follow up only the prospects they are assigned to', function (): void {
    $prospect = app(CreateProspect::class)->handle(prospectData(['primaryDaiId' => $this->otherDai->id]), $this->head);

    expectRuleViolation(fn () => app(RecordProspectFollowUp::class)->handle($prospect, followUp(), $this->daiUser), 'dai_id');

    $noDaiRecord = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    expect(fn () => app(RecordProspectFollowUp::class)->handle($prospect, followUp(), $noDaiRecord))->toThrow(DaiRuleViolation::class);
});

it('shows confidential follow-ups only to their author and to holders of followups.view_confidential', function (): void {
    $prospect = app(CreateProspect::class)->handle(prospectData(), $this->daiUser);
    app(RecordProspectFollowUp::class)->handle($prospect, followUp(['confidentiality' => Confidentiality::Confidential]), $this->daiUser);
    app(RecordProspectFollowUp::class)->handle($prospect, followUp(), $this->daiUser);
    $auditor = grantRole(memberOf($this->dammam), 'local_auditor', $this->dammam);

    $visible = fn (User $user): int => DaiFollowUp::query()->visibleFor($prospect, $user)->count();

    expect($visible($this->daiUser))->toBe(2)
        ->and($visible($this->head))->toBe(2)
        ->and($visible($auditor))->toBe(1);
});

it('keeps one active primary assignment per prospect in the database', function (): void {
    $prospect = Prospect::factory()->create(['primary_dai_id' => $this->dai->id]);
    DaiAssignment::factory()->create(['dai_id' => $this->dai->id, 'subject_id' => $prospect->id]);

    expect(fn () => DaiAssignment::factory()->create(['dai_id' => $this->otherDai->id, 'subject_id' => $prospect->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});
