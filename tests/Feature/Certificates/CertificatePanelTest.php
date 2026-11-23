<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Actions\VerifyShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Certificates\Pages\ManageCertificates;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CertificateTemplateSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->seed(CertificateTemplateSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);

    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->staff = fn (string $role): User => withAuthenticator(grantRole(memberOf($this->tenant), $role, $this->tenant));
    $this->convertId = inTenant($this->tenant, function (): int {
        $registration = app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
            profile: new ConvertProfileData('Daniel Mendoza', Gender::Male, Country::factory()->create()->id),
            identity: new IdentifierData(IdentityType::Iqamah, saudiId('299001122')),
            shahadahDate: CarbonImmutable::today(),
            location: 'Center hall',
            primaryDaiId: Dai::factory()->create()->id,
            witnesses: shahadahWitnesses(),
        ), ($this->staff)('registration_officer'));
        app(VerifyShahadah::class)->handle($registration, ($this->staff)('shahadah_verifier'));

        return $registration->convert_id;
    });
});

it('takes a shahadah certificate from request to download through the panel', function (): void {
    inCenterPanel($this->tenant, ($this->staff)('guidance_coordinator'));
    Livewire::test(ManageCertificates::class)
        ->callAction(TestAction::make('request')->table(), ['certificate_type' => 'shahadah', 'convert_id' => $this->convertId])
        ->assertHasNoActionErrors();
    $certificate = inTenant($this->tenant, fn () => Certificate::query()->sole());

    inCenterPanel($this->tenant, ($this->staff)('approver'));
    Livewire::test(ManageCertificates::class)->callAction(TestAction::make('approve')->table($certificate));

    $officer = ($this->staff)('certificate_officer');
    inCenterPanel($this->tenant, $officer);
    Livewire::test(ManageCertificates::class)
        ->assertActionHidden(TestAction::make('approve')->table($certificate))
        ->callAction(TestAction::make('issue')->table($certificate))
        ->assertHasNoActionErrors();

    $certificate = inTenant($this->tenant, fn () => $certificate->refresh());
    expect($certificate->status)->toBe(CertificateStatus::Issued)->and($certificate->hasPdf())->toBeTrue();

    Livewire::test(ManageCertificates::class)
        ->callAction(TestAction::make('download')->table($certificate))
        ->assertFileDownloaded($certificate->certificate_number.'.pdf');

    expect(AuditLog::query()->where('action', 'certificate.downloaded')->sole()->actor_id)->toBe($officer->id);
});

it('opens the national templates in Central to template managers only', function (): void {
    $this->actingAs(withAuthenticator(grantRole(User::factory()->create(), 'central_admin')))->get('/central/certificate-templates')->assertOk();
    $this->actingAs(withAuthenticator(grantRole(User::factory()->create(), 'central_auditor')))->get('/central/certificate-templates')->assertForbidden();
});

it('limits public verification to 60 requests a minute per address', function (): void {
    $url = route('certificates.verify', ['uuid' => fake()->uuid(), 't' => 'x']);

    foreach (range(1, 60) as $attempt) {
        $this->get($url)->assertOk();
    }

    $this->get($url)->assertTooManyRequests();
});
