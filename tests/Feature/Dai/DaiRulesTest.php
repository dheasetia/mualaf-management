<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditRedactor;
use App\Domain\Dai\Actions\ChangeDaiStatus;
use App\Domain\Dai\Actions\CreateDai;
use App\Domain\Dai\Actions\UpdateDai;
use App\Domain\Dai\Data\DaiData;
use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Enums\EmploymentStatus;
use App\Domain\Dai\Enums\LanguageProficiency;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Language;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    [$this->dammam, $this->riyadh] = Tenant::factory()->active()->count(2)->create();
    $this->actor = User::factory()->create();
    app(TenantContext::class)->set($this->dammam);
});

function daiData(array $overrides = []): DaiData
{
    return new DaiData(...[
        'employeeNumber' => 'D-100',
        'fullName' => 'Abdullah Santos',
        'fullNameAr' => 'عبدالله سانتوس',
        'gender' => Gender::Male,
        'employmentStatus' => EmploymentStatus::FullTime,
        ...$overrides,
    ]);
}

function expectViolation(Closure $action, string $field): void
{
    expect($action)->toThrow(fn (DaiRuleViolation $violation) => expect($violation->field)->toBe($field));
}

it('registers a da\'i with languages, encrypts the phone and audits without it', function (): void {
    $tagalog = Language::factory()->create(['code' => 'tl']);

    $dai = app(CreateDai::class)->handle(daiData([
        'phone' => '+966500000001',
        'languages' => [$tagalog->id => LanguageProficiency::Native],
    ]), $this->actor);

    expect($dai->status)->toBe(DaiStatus::Active)
        ->and($dai->tenant_id)->toBe($this->dammam->id)
        ->and($dai->languages()->sole()->code)->toBe('tl')
        ->and(DB::table('dais')->where('id', $dai->id)->value('phone_encrypted'))->not->toContain('500000001');

    $entry = AuditLog::query()->where('action', 'dai.created')->sole();
    expect($entry->subject_type)->toBe('dai')
        ->and($entry->new_values_redacted['phone_encrypted'])->toBe(AuditRedactor::MASK)
        ->and($entry->new_values_redacted['languages'])->toBe([(string) $tagalog->id => 'native']);
});

it('keeps employee numbers unique per office only', function (): void {
    app(CreateDai::class)->handle(daiData(), $this->actor);

    expectViolation(fn () => app(CreateDai::class)->handle(daiData(['fullName' => 'Other']), $this->actor), 'employee_number');

    $riyadhDai = inTenant($this->riyadh, fn (): Dai => app(CreateDai::class)->handle(daiData(), $this->actor));
    expect($riyadhDai->tenant_id)->toBe($this->riyadh->id);
});

it('links only user accounts that are members of the office, once', function (): void {
    $member = memberOf($this->dammam);

    expectViolation(fn () => app(CreateDai::class)->handle(daiData(['userId' => memberOf($this->riyadh)->id]), $this->actor), 'user_id');

    app(CreateDai::class)->handle(daiData(['userId' => $member->id]), $this->actor);
    expectViolation(fn () => app(CreateDai::class)->handle(daiData(['employeeNumber' => 'D-101', 'userId' => $member->id]), $this->actor), 'user_id');
});

it('accepts only an active supervisor of the same office', function (): void {
    $onLeave = Dai::factory()->create(['status' => DaiStatus::OnLeave]);
    $otherOffice = inTenant($this->riyadh, fn (): Dai => Dai::factory()->create());

    foreach ([$onLeave->id, $otherOffice->id] as $supervisorId) {
        expectViolation(fn () => app(CreateDai::class)->handle(daiData(['supervisorDaiId' => $supervisorId]), $this->actor), 'supervisor_dai_id');
    }
});

it('rejects a supervisor chain that leads back to the da\'i', function (): void {
    $head = Dai::factory()->create();
    $middle = Dai::factory()->create(['supervisor_dai_id' => $head->id]);
    Dai::factory()->create(['supervisor_dai_id' => $middle->id]);

    expectViolation(fn () => app(UpdateDai::class)->handle($head, daiData(['employeeNumber' => $head->employee_number, 'supervisorDaiId' => $middle->id]), $this->actor), 'supervisor_dai_id');
    expectViolation(fn () => app(UpdateDai::class)->handle($head, daiData(['employeeNumber' => $head->employee_number, 'supervisorDaiId' => $head->id]), $this->actor), 'supervisor_dai_id');
});

it('audits only what an update changed, languages included', function (): void {
    $urdu = Language::factory()->create(['code' => 'ur']);
    $dai = app(CreateDai::class)->handle(daiData(), $this->actor);

    app(UpdateDai::class)->handle($dai, daiData(['education' => 'Umm al-Qura University', 'languages' => [$urdu->id => LanguageProficiency::Fluent]]), $this->actor);
    app(UpdateDai::class)->handle($dai->refresh(), daiData(['education' => 'Umm al-Qura University', 'languages' => [$urdu->id => LanguageProficiency::Fluent]]), $this->actor);

    $entry = AuditLog::query()->where('action', 'dai.updated')->sole();
    expect($entry->old_values_redacted)->toEqual(['education' => null, 'languages' => []])
        ->and($entry->new_values_redacted)->toEqual(['education' => 'Umm al-Qura University', 'languages' => [(string) $urdu->id => 'fluent']]);
});

it('follows the status transitions and audits each change', function (): void {
    $dai = Dai::factory()->create();

    app(ChangeDaiStatus::class)->handle($dai, DaiStatus::OnLeave, $this->actor, 'Hajj leave');
    app(ChangeDaiStatus::class)->handle($dai, DaiStatus::Inactive, $this->actor);

    expect($dai->refresh()->status)->toBe(DaiStatus::Inactive)
        ->and(AuditLog::query()->where('action', 'dai.status_changed')->count())->toBe(2);

    expectViolation(fn () => app(ChangeDaiStatus::class)->handle($dai, DaiStatus::OnLeave, $this->actor), 'status');
});

it('does not deactivate a da\'i who is still primary for someone', function (): void {
    $dai = Dai::factory()->create();
    DaiAssignment::factory()->for($dai)->create();
    DaiAssignment::factory()->for($dai)->ended()->create();

    expectViolation(fn () => app(ChangeDaiStatus::class)->handle($dai, DaiStatus::Inactive, $this->actor), 'status');

    DaiAssignment::query()->where('dai_id', $dai->id)->update(['ended_at' => now()]);
    expect(app(ChangeDaiStatus::class)->handle($dai, DaiStatus::Inactive, $this->actor)->status)->toBe(DaiStatus::Inactive);
});
