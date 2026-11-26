<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\CorrectShahadahRegistration;
use App\Domain\Registration\Actions\ReturnShahadahRegistration;
use App\Domain\Registration\Actions\ShahadahVoiding;
use App\Domain\Registration\Actions\VerifyShahadah;
use App\Domain\Registration\Data\ShahadahCorrectionData;
use App\Domain\Registration\Data\ShahadahWitnessData;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Enums\ShahadahStatus;
use App\Domain\Registration\Enums\VoidRequestStatus;
use App\Domain\Registration\Events\ShahadahRecorded;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Registration\Models\ShahadahVoidRequest;
use App\Domain\Registration\Models\ShahadahWitness;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Pages\RegisterShahadah;
use App\Filament\Center\Resources\ConvertRegistrations\Pages\ManageConvertRegistrations;
use App\Filament\Central\Resources\ShahadahVoidRequests\Pages\ListShahadahVoidRequests;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->convert = documentsConvert($this->dammam, verified: false, identity: '277001122');
    $this->registration = fn (): ConvertRegistration => inTenant($this->dammam, fn () => ConvertRegistration::query()->where('convert_id', $this->convert->id)->sole());
    $this->registrar = User::query()->findOrFail(($this->registration)()->registered_by);
    $this->verifier = grantRole(memberOf($this->dammam), 'shahadah_verifier', $this->dammam);
    $this->head = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    $this->centralAdmin = grantRole(User::factory()->create(), 'central_admin');
    $this->correction = fn (array $overrides = []): ShahadahCorrectionData => new ShahadahCorrectionData(...[
        'profile' => new ConvertProfileData('Daniel Mendes', Gender::Male, $this->convert->nationality_id, islamicName: 'Dawud'),
        'shahadahDate' => CarbonImmutable::parse('2026-11-02'),
        'location' => 'Main hall',
        'witnesses' => [new ShahadahWitnessData('Saad Al-Dosari', '0551234011'), new ShahadahWitnessData('Faisal Al-Shehri', '0551234012')],
        ...$overrides,
    ]);
    $this->returnIt = fn () => inTenant($this->dammam, fn () => app(ReturnShahadahRegistration::class)->handle(($this->registration)(), $this->verifier, 'Location and witnesses are wrong'));
    $this->verify = fn (): ConvertRegistration => inTenant($this->dammam, fn () => app(VerifyShahadah::class)->handle(($this->registration)(), $this->verifier));
});

it('returns a pending registration to its registrar with a note, never by the registrar', function (): void {
    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(ReturnShahadahRegistration::class)->handle(($this->registration)(), $this->verifier, ' '), 'note'));

    ($this->returnIt)();

    expect(($this->registration)())->status->toBe(RegistrationStatus::Returned)->rejection_reason->toBe('Location and witnesses are wrong')
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->registrar->id)->sole()->data['kind'])->toBe('shahadah_returned')
        ->and(AuditLog::query()->where('action', 'shahadah.returned')->count())->toBe(1);

    inTenant($this->dammam, fn () => expectRuleViolation(fn () => ($this->verify)(), 'registration'));
});

it('sends a corrected registration back for verification, by someone other than its verifier', function (): void {
    Event::fake([ShahadahRecorded::class]);
    ($this->returnIt)();

    inTenant($this->dammam, fn () => app(CorrectShahadahRegistration::class)->handle(($this->registration)(), ($this->correction)([
        'identity' => new IdentifierData(IdentityType::Iqamah, saudiId('277001130')),
    ]), $this->registrar));

    inTenant($this->dammam, function (): void {
        $registration = ($this->registration)();
        $shahadah = ShahadahRecord::query()->sole();

        expect($registration)->status->toBe(RegistrationStatus::PendingVerification)->corrected_by->toBe($this->registrar->id)->rejection_reason->toBeNull()
            ->and(Convert::query()->sole()->full_name)->toBe('Daniel Mendes')
            ->and($shahadah)->location->toBe('Main hall')->and($shahadah->shahadah_date->toDateString())->toBe('2026-11-02')
            ->and(ShahadahWitness::query()->orderBy('id')->pluck('name')->all())->toBe(['Saad Al-Dosari', 'Faisal Al-Shehri'])
            ->and(ConvertIdentifier::query()->whereNull('replaced_at')->sole()->identity_number_encrypted)->toBe(saudiId('277001130'))
            ->and(app(IdentityRegistry::class)->findActive(new IdentifierData(IdentityType::Iqamah, saudiId('277001122'))))->toBeNull();
    });
    Event::assertDispatched(ShahadahRecorded::class);

    $corrector = inTenant($this->dammam, fn () => grantRole($this->registrar, 'shahadah_verifier', $this->dammam));
    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(VerifyShahadah::class)->handle(($this->registration)(), $corrector), 'registration'));
    expect(($this->verify)()->status)->toBe(RegistrationStatus::Verified);
});

it('corrects only returned registrations and keeps the old number when the new one is registered elsewhere', function (): void {
    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(CorrectShahadahRegistration::class)->handle(($this->registration)(), ($this->correction)(), $this->registrar), 'registration'));

    documentsConvert($this->dammam, verified: false, identity: '277001148');
    ($this->returnIt)();

    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(CorrectShahadahRegistration::class)->handle(($this->registration)(), ($this->correction)([
        'identity' => new IdentifierData(IdentityType::Iqamah, saudiId('277001148')),
    ]), $this->registrar), 'identity_number'));

    inTenant($this->dammam, fn () => expect(ConvertRegistration::query()->where('convert_id', $this->convert->id)->sole()->status)->toBe(RegistrationStatus::Returned)
        ->and(Convert::query()->findOrFail($this->convert->id)->full_name)->toBe('Daniel Mendoza')
        ->and(app(IdentityRegistry::class)->findActive(new IdentifierData(IdentityType::Iqamah, saudiId('277001122')))?->convert_id)->toBe($this->convert->id));
});

it('corrects a returned registration from the registration page', function (): void {
    ($this->returnIt)();
    inCenterPanel($this->dammam, $this->registrar);

    Livewire::test(RegisterShahadah::class, ['registration' => ($this->registration)()->id])
        ->assertFormSet(['full_name' => 'Daniel Mendoza', 'location' => 'Center hall', 'replace_identity' => false])
        ->fillForm(['location' => 'Main hall'])
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(($this->registration)()->status)->toBe(RegistrationStatus::PendingVerification)
        ->and(inTenant($this->dammam, fn () => ShahadahRecord::query()->sole()->location))->toBe('Main hall');

    $this->get(RegisterShahadah::getUrl(['registration' => ($this->registration)()->id], tenant: $this->dammam))->assertForbidden();
});

it('voids a verified shahadah on Central approval: certificate revoked, record closed, identity freed', function (): void {
    ($this->verify)();
    $certificate = inTenant($this->dammam, fn () => Certificate::factory()->create([
        'convert_id' => $this->convert->id, 'tenant_id' => $this->dammam->id, 'status' => CertificateStatus::Issued, 'certificate_type' => CertificateType::Shahadah,
    ]));

    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(ShahadahVoiding::class)->request(($this->registration)(), $this->head, ''), 'note'));
    $request = inTenant($this->dammam, fn () => app(ShahadahVoiding::class)->request(($this->registration)(), $this->head, 'Registered twice under different documents'));
    inTenant($this->dammam, fn () => expectRuleViolation(fn () => app(ShahadahVoiding::class)->request(($this->registration)(), $this->head, 'Again'), 'registration'));
    expect(DatabaseNotification::query()->where('notifiable_id', $this->centralAdmin->id)->sole()->data['kind'])->toBe('shahadah_void_requested');

    app(ShahadahVoiding::class)->approve($request, $this->centralAdmin, 'Confirmed with the center');

    inTenant($this->dammam, function () use ($certificate): void {
        expect(ConvertRegistration::query()->withoutGlobalScopes()->sole()->status)->toBe(RegistrationStatus::Voided)
            ->and(ShahadahRecord::query()->withoutGlobalScopes()->sole()->status)->toBe(ShahadahStatus::Voided)
            ->and(Convert::query()->findOrFail($this->convert->id)->status)->toBe(ConvertStatus::RecordClosed)
            ->and(Certificate::query()->findOrFail($certificate->id))->status->toBe(CertificateStatus::Revoked)->revoked_by->toBe($this->centralAdmin->id)
            ->and(app(IdentityRegistry::class)->findActive(new IdentifierData(IdentityType::Iqamah, saudiId('277001122'))))->toBeNull();
    });
    expect($request->refresh())->status->toBe(VoidRequestStatus::Approved)->decided_by->toBe($this->centralAdmin->id)
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->head->id)->sole()->data['kind'])->toBe('shahadah_void_approved')
        ->and(AuditLog::query()->where('action', 'shahadah.voided')->sole()->new_values_redacted['certificates_closed'])->toBe(1);

    // The person can be registered again.
    documentsConvert($this->dammam, verified: false, identity: '277001122');
    expect(inTenant($this->dammam, fn () => Convert::query()->where('status', ConvertStatus::Active)->count()))->toBe(1);
});

it('leaves everything in place when Central rejects a void request, and never lets the requester decide', function (): void {
    $request = inTenant($this->dammam, fn () => app(ShahadahVoiding::class)->request(($this->registration)(), $this->head, 'Mistake'));

    $both = grantRole($this->head, 'central_admin');
    expectRuleViolation(fn () => app(ShahadahVoiding::class)->approve($request, $both), 'request');
    expectRuleViolation(fn () => app(ShahadahVoiding::class)->reject($request, $this->centralAdmin, ''), 'note');

    app(ShahadahVoiding::class)->reject($request, $this->centralAdmin, 'Correct it instead');

    expect($request->refresh()->status)->toBe(VoidRequestStatus::Rejected)
        ->and(($this->registration)()->status)->toBe(RegistrationStatus::PendingVerification)
        ->and(inTenant($this->dammam, fn () => Convert::query()->findOrFail($this->convert->id)->status))->toBe(ConvertStatus::Active);
});

it('offers return, correction and void requests in the office panel to the right people only', function (): void {
    inCenterPanel($this->dammam, $this->verifier);
    Livewire::test(ManageConvertRegistrations::class)
        ->assertActionHidden(TestAction::make('requestVoid')->table(($this->registration)()))
        ->callAction(TestAction::make('return')->table(($this->registration)()), ['note' => 'Fix the place'])
        ->assertHasNoActionErrors();

    inCenterPanel($this->dammam, $this->registrar);
    Livewire::test(ManageConvertRegistrations::class)
        ->assertActionVisible(TestAction::make('correct')->table(($this->registration)()))
        ->assertActionHidden(TestAction::make('return')->table(($this->registration)()));

    inCenterPanel($this->dammam, $this->head);
    Livewire::test(ManageConvertRegistrations::class)
        ->callAction(TestAction::make('requestVoid')->table(($this->registration)()), ['reason' => 'Duplicate person'])
        ->assertHasNoActionErrors();

    $this->actingAs($this->centralAdmin);
    app(TenantContext::class)->clear();
    Filament::setTenant(null);
    Filament::setCurrentPanel(Filament::getPanel('central'));
    Livewire::test(ListShahadahVoidRequests::class)
        ->assertCanSeeTableRecords([ShahadahVoidRequest::query()->sole()])
        ->callAction(TestAction::make('reject')->table(ShahadahVoidRequest::query()->sole()), ['note' => 'Correct it instead'])
        ->assertHasNoActionErrors();

    expect(ShahadahVoidRequest::query()->sole()->status)->toBe(VoidRequestStatus::Rejected);
});
