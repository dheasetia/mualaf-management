<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Actions\AddConvertContact;
use App\Domain\Converts\Actions\AssignConvertDai;
use App\Domain\Converts\Actions\ChangeConvertStatus;
use App\Domain\Converts\Actions\ChangeDisclosureLevel;
use App\Domain\Converts\Actions\RemoveConvertContact;
use App\Domain\Converts\Actions\UpdateConvert;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Enums\ContactType;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Enums\ProfileAccess;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContact;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Services\DaiAssignmentService;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Religion;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create();
    app(TenantContext::class)->set($this->dammam);

    $this->role = fn (string $role): User => grantRole(memberOf($this->dammam), $role, $this->dammam);
    $this->head = ($this->role)('head_of_dawah');
    $this->daiUser = ($this->role)('dai');
    $this->dai = Dai::factory()->create(['user_id' => $this->daiUser->id]);

    $this->convert = function (DisclosureLevel $level = DisclosureLevel::Normal): Convert {
        $convert = Convert::factory()->create(['full_name' => 'Abdullah Reyes', 'islamic_name' => 'Abdullah', 'disclosure_risk_level' => $level, 'primary_dai_id' => $this->dai->id]);
        app(DaiAssignmentService::class)->assignPrimary($convert, $this->dai->id, $this->head);

        return $convert->refresh();
    };
});

it('gives each viewer the profile the disclosure level allows', function (DisclosureLevel $level, string $viewer, ProfileAccess $expected): void {
    $convert = ($this->convert)($level);
    $user = match ($viewer) {
        'primary da\'i' => $this->daiUser,
        default => ($this->role)($viewer),
    };

    expect(app(ConvertAccess::class)->profile($user, $convert))->toBe($expected);
})->with([
    'normal, registration officer' => [DisclosureLevel::Normal, 'registration_officer', ProfileAccess::Full],
    'restricted, registration officer' => [DisclosureLevel::Restricted, 'registration_officer', ProfileAccess::Minimal],
    'restricted, center admin' => [DisclosureLevel::Restricted, 'center_admin', ProfileAccess::Full],
    'restricted, primary da\'i' => [DisclosureLevel::Restricted, 'primary da\'i', ProfileAccess::Full],
    'high, center admin' => [DisclosureLevel::HighConfidentiality, 'center_admin', ProfileAccess::Hidden],
    'high, transportation officer' => [DisclosureLevel::HighConfidentiality, 'transportation_officer', ProfileAccess::Hidden],
    'high, head of dawah' => [DisclosureLevel::HighConfidentiality, 'head_of_dawah', ProfileAccess::Full],
    'high, primary da\'i' => [DisclosureLevel::HighConfidentiality, 'primary da\'i', ProfileAccess::Full],
]);

it('names the convert as far as the viewer may see', function (): void {
    $restricted = ($this->convert)(DisclosureLevel::Restricted);
    $hidden = ($this->convert)(DisclosureLevel::HighConfidentiality);
    $registrar = ($this->role)('registration_officer');
    $access = app(ConvertAccess::class);

    expect($access->displayName($registrar, $restricted))->toBe('Abdullah')
        ->and($access->displayName($registrar, $hidden))->toBe($hidden->alias())
        ->and($access->displayName($this->head, $hidden))->toBe('Abdullah Reyes');
});

it('shows a da\'i only the converts they are assigned to', function (): void {
    $mine = ($this->convert)();
    Convert::factory()->create();

    expect(app(ConvertAccess::class)->visible(Convert::query(), $this->daiUser)->pluck('id')->all())->toBe([$mine->id]);
});

it('keeps previous religion to holders of converts.view_sensitive', function (): void {
    $convert = ($this->convert)();
    $religion = Religion::factory()->create();
    $data = new ConvertProfileData('Abdullah Reyes', Gender::Male, $convert->nationality_id, previousReligionId: $religion->id);

    app(UpdateConvert::class)->handle($convert, $data, ($this->role)('registration_officer'));
    expect($convert->refresh()->previous_religion_id)->toBeNull()
        ->and(app(ConvertAccess::class)->canSeeSensitive(($this->role)('registration_officer'), $convert))->toBeFalse();

    app(UpdateConvert::class)->handle($convert, $data, $this->head);
    expect($convert->refresh()->previous_religion_id)->toBe($religion->id)
        ->and(AuditLog::query()->where('action', 'converts.updated')->latest('id')->first()->new_values_redacted)->toBe(['previous_religion_id' => $religion->id]);
});

it('changes the disclosure level only with a reason, audited', function (): void {
    $convert = ($this->convert)();

    expectRuleViolation(fn () => app(ChangeDisclosureLevel::class)->handle($convert, DisclosureLevel::Restricted, $this->head, ' '), 'reason');

    app(ChangeDisclosureLevel::class)->handle($convert, DisclosureLevel::Restricted, $this->head, 'Family pressure');

    expect($convert->refresh()->disclosure_risk_level)->toBe(DisclosureLevel::Restricted)
        ->and(AuditLog::query()->where('action', 'converts.disclosure_changed')->sole()->new_values_redacted)
        ->toEqual(['disclosure_risk_level' => 'restricted', 'reason' => 'Family pressure']);
});

it('records status changes with history and keeps closed records read-only', function (): void {
    $convert = ($this->convert)();

    app(ChangeConvertStatus::class)->handle($convert, ConvertStatus::Inactive, $this->head, 'Moved, no contact');
    app(ChangeConvertStatus::class)->handle($convert, ConvertStatus::RecordClosed, $this->head, 'Duplicate paper record');

    expect($convert->statusHistory()->pluck('to_status')->map->value->all())->toBe(['inactive', 'record_closed'])
        ->and($convert->statusHistory()->first()->tenant_id)->toBe($this->dammam->id);

    expectRuleViolation(fn () => app(ChangeConvertStatus::class)->handle($convert->refresh(), ConvertStatus::Active, $this->head, 'Reopen'), 'status');
    expectRuleViolation(fn () => app(UpdateConvert::class)->handle($convert, new ConvertProfileData('X', Gender::Male, $convert->nationality_id), $this->head), 'status');
    expectRuleViolation(fn () => app(AssignConvertDai::class)->handle($convert, $this->dai->id, $this->head), 'status');
});

it('stores contacts encrypted and normalised, with one primary', function (): void {
    $convert = ($this->convert)();
    $add = app(AddConvertContact::class);

    $first = $add->handle($convert, ContactType::Mobile, '050 123 4567', $this->head, primary: true);
    $second = $add->handle($convert, ContactType::Whatsapp, '+63 917 123 4567', $this->head, primary: true);

    expect($first->refresh()->value_encrypted)->toBe('+966501234567')
        ->and($first->is_primary)->toBeFalse()
        ->and($second->refresh()->is_primary)->toBeTrue()
        ->and(DB::table('convert_contacts')->where('id', $first->id)->value('value_encrypted'))->not->toContain('501234567');

    expectRuleViolation(fn () => $add->handle($convert, ContactType::Email, 'not-an-email', $this->head), 'value');

    app(RemoveConvertContact::class)->handle($second, $this->head);
    expect(ConvertContact::query()->where('active', true)->pluck('id')->all())->toBe([$first->id]);
});

it('gives converts registered at the same moment different aliases', function (): void {
    $tenant = Tenant::factory()->active()->create();

    $aliases = inTenant($tenant, fn () => Convert::factory()->count(5)->create()->map(fn ($convert) => $convert->alias())->unique());

    expect($aliases)->toHaveCount(5);
});
