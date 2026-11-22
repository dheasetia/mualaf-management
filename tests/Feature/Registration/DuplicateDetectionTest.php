<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Actions\ResolveDuplicateCase;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Enums\MatchReason;
use App\Domain\Registration\Enums\ReviewDecision;
use App\Domain\Registration\Enums\ReviewStatus;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Domain\Registration\Services\DuplicateReview;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\DuplicateReviewCases\Pages\ManageDuplicateReviewCases;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->philippines = Country::factory()->create();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->registrar = withAuthenticator(grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam));
    $this->head = withAuthenticator(grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam));
    $this->dai = inTenant($this->dammam, fn () => Dai::factory()->create());

    // Already registered in Riyadh under a passport.
    $this->existing = inTenant($this->riyadh, function (): Convert {
        $registration = app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
            profile: new ConvertProfileData('Jose Reyes Santos', Gender::Male, $this->philippines->id, dateOfBirth: CarbonImmutable::parse('1990-05-17')),
            identity: new IdentifierData(IdentityType::Passport, 'P1234567', $this->philippines->id),
            shahadahDate: CarbonImmutable::today()->subYear(),
            location: 'Riyadh center',
            primaryDaiId: Dai::factory()->create()->id,
            phone: '0551234000',
        ), grantRole(memberOf($this->riyadh), 'registration_officer', $this->riyadh));

        return Convert::query()->findOrFail($registration->convert_id);
    });

    // The same person, now with an Iqamah, at another office.
    $this->attempt = fn (array $overrides = []) => inTenant($this->dammam, fn () => app(RecordShahadah::class)->handle(new ShahadahRegistrationData(...[
        'profile' => new ConvertProfileData('Jose Santos', Gender::Male, $this->philippines->id, dateOfBirth: CarbonImmutable::parse('1990-05-17')),
        'identity' => new IdentifierData(IdentityType::Iqamah, saudiId('256789123')),
        'shahadahDate' => CarbonImmutable::today(),
        'location' => 'Dammam center',
        'primaryDaiId' => $this->dai->id,
        ...$overrides,
    ]), $this->registrar));
    $this->case = fn (): DuplicateReviewCase => inTenant($this->dammam, fn () => DuplicateReviewCase::query()->sole());
});

it('holds a registration that looks like a convert of another office and opens a review case', function (): void {
    expectRuleViolation($this->attempt, 'full_name');

    $case = ($this->case)();
    expect($case)
        ->tenant_id->toBe($this->dammam->id)
        ->possible_convert_id->toBe($this->existing->id)
        ->match_reason->toBe(MatchReason::FuzzyNameDob)
        ->status->toBe(ReviewStatus::Open)
        ->and((float) $case->match_score)->toBeGreaterThanOrEqual(0.85)
        ->and($case->match_details['date_of_birth_match'])->toBeTrue()
        ->and(inTenant($this->dammam, fn () => Convert::query()->count()))->toBe(0)
        ->and(AuditLog::query()->where('action', 'duplicates.case_opened')->count())->toBe(1);
});

it('keeps the registration held while the case is open, without opening another', function (): void {
    expectRuleViolation($this->attempt, 'full_name');
    expectRuleViolation($this->attempt, 'full_name');

    expect(inTenant($this->dammam, fn () => DuplicateReviewCase::query()->count()))->toBe(1);
});

it('lets the registration through once the reviewer decides it is a different person', function (): void {
    expectRuleViolation($this->attempt, 'full_name');
    inTenant($this->dammam, fn () => app(ResolveDuplicateCase::class)->handle(($this->case)(), ReviewDecision::DifferentPerson, $this->head, 'Different father name and photo'));

    expect(($this->attempt)()->exists)->toBeTrue();
});

it('keeps the registration blocked for the same person, and while inconclusive', function (ReviewDecision $decision): void {
    expectRuleViolation($this->attempt, 'full_name');
    inTenant($this->dammam, fn () => app(ResolveDuplicateCase::class)->handle(($this->case)(), $decision, $this->head, 'Reviewed'));

    expectRuleViolation($this->attempt, 'full_name');
})->with([ReviewDecision::SamePerson, ReviewDecision::Inconclusive]);

it('flags a phone number already used by a convert', function (): void {
    expectRuleViolation(fn () => ($this->attempt)(['profile' => new ConvertProfileData('Somebody Else', Gender::Female, Country::factory()->create()->id), 'phone' => '+966 55 123 4000']), 'full_name');

    expect(($this->case)()->match_reason)->toBe(MatchReason::PhoneMatch);
});

it('lets different people through', function (): void {
    expect(($this->attempt)(['profile' => new ConvertProfileData('Maria Cruz', Gender::Female, $this->philippines->id, dateOfBirth: CarbonImmutable::parse('1995-01-01'))])->exists)->toBeTrue();
});

it('leaves high-confidentiality converts out of name matching', function (): void {
    inTenant($this->riyadh, fn () => $this->existing->forceFill(['disclosure_risk_level' => DisclosureLevel::HighConfidentiality])->save());

    expect(($this->attempt)()->exists)->toBeTrue();
});

it('requires notes and does not decide a resolved case twice', function (): void {
    expectRuleViolation($this->attempt, 'full_name');

    inTenant($this->dammam, function (): void {
        expectRuleViolation(fn () => app(ResolveDuplicateCase::class)->handle(($this->case)(), ReviewDecision::SamePerson, $this->head, ''), 'notes');
        app(ResolveDuplicateCase::class)->handle(($this->case)(), ReviewDecision::SamePerson, $this->head, 'Same photo');
        expectRuleViolation(fn () => app(ResolveDuplicateCase::class)->handle(($this->case)(), ReviewDecision::DifferentPerson, $this->head, 'Changed my mind'), 'decision');
    });
});

it('shows the reviewer only initials and office of a convert of another office', function (): void {
    expectRuleViolation($this->attempt, 'full_name');

    $this->actingAs($this->registrar)->get('/center/DMM/duplicate-review-cases')->assertForbidden();

    expect(inTenant($this->dammam, fn () => app(DuplicateReview::class)->possibleConvert(($this->case)())))
        ->name->toBe('J. R. S.')
        ->office->toContain('RUH')
        ->same_office->toBeFalse();

    inCenterPanel($this->dammam, $this->head);
    Livewire::test(ManageDuplicateReviewCases::class)
        ->assertCanSeeTableRecords([($this->case)()])
        ->assertDontSee('Jose Reyes Santos');

    Livewire::test(ManageDuplicateReviewCases::class)
        ->callAction(TestAction::make('resolve')->table(($this->case)()), ['decision' => 'different_person', 'notes' => 'Checked'])
        ->assertHasNoActionErrors();

    expect(($this->case)()->status)->toBe(ReviewStatus::Resolved);
});
