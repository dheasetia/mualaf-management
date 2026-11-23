<?php

declare(strict_types=1);

use App\Domain\Certificates\Actions\CertificateWorkflow;
use App\Domain\Certificates\Actions\ManageTemplates;
use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Jobs\GenerateCertificatePdf;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Domain\Certificates\Services\CertificateVerifier;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Actions\VerifyShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Pdf\TemplateRenderer;
use Carbon\CarbonImmutable;
use Database\Seeders\CertificateTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
    $this->seed(CertificateTemplateSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $staff = fn (string $role): User => grantRole(memberOf($this->tenant), $role, $this->tenant);
    $this->requester = $staff('guidance_coordinator');
    $this->approver = $staff('approver');
    $this->officer = $staff('certificate_officer');

    $this->convert = inTenant($this->tenant, function () use ($staff): Convert {
        $registration = app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
            profile: new ConvertProfileData('Daniel Mendoza', Gender::Male, Country::factory()->create()->id, islamicName: 'Dawud'),
            identity: new IdentifierData(IdentityType::Iqamah, saudiId('288990011')),
            shahadahDate: CarbonImmutable::parse('2026-11-01'),
            location: 'Center hall',
            primaryDaiId: Dai::factory()->create()->id,
            witnesses: shahadahWitnesses(),
        ), $staff('registration_officer'));

        return Convert::query()->findOrFail($registration->convert_id);
    });
    $this->verifyShahadah = fn () => inTenant($this->tenant, fn () => app(VerifyShahadah::class)->handle(
        ConvertRegistration::query()->sole(), grantRole(memberOf($this->tenant), 'shahadah_verifier', $this->tenant)));

    $this->workflow = fn () => app(CertificateWorkflow::class);
    $this->issueShahadah = function (): Certificate {
        ($this->verifyShahadah)();

        return inTenant($this->tenant, function (): Certificate {
            $certificate = ($this->workflow)()->request(CertificateType::Shahadah, $this->convert->id, null, $this->requester);
            ($this->workflow)()->approve($certificate, $this->approver);

            return ($this->workflow)()->issue($certificate, $this->officer);
        });
    };
});

it('issues a shahadah certificate with a number, frozen snapshot and hashed token, then queues the PDF', function (): void {
    Queue::fake();

    $certificate = ($this->issueShahadah)();

    expect($certificate)
        ->status->toBe(CertificateStatus::Issued)
        ->certificate_number->toBe('DMM-SH-'.now()->format('Y').'-000001')
        ->and(strlen((string) $certificate->verification_token_hash))->toBe(32)
        ->and($certificate->snapshot_data)->toMatchArray(['full_name' => 'Daniel Mendoza', 'shahadah_date' => '2026-11-01', 'certificate_number' => $certificate->certificate_number])
        ->and($certificate->snapshot_data['shahadah_date_hijri_ar'])->toContain('١٤٤٨')
        ->and($certificate->snapshot_data['shahadah_date_gregorian_en'])->toBe('November 1, 2026')
        ->and($certificate->snapshot_data['shahadah_date_hijri_en'])->toContain('1448 AH');

    Queue::assertPushedOn('pdf', GenerateCertificatePdf::class);
});

it('requires a verified shahadah, an approver other than the requester, and one live certificate per type', function (): void {
    inTenant($this->tenant, fn () => expectRuleViolation(fn () => ($this->workflow)()->request(CertificateType::Shahadah, $this->convert->id, null, $this->requester), 'convert_id'));

    ($this->verifyShahadah)();

    inTenant($this->tenant, function (): void {
        $certificate = ($this->workflow)()->request(CertificateType::Shahadah, $this->convert->id, null, $this->requester);
        expectRuleViolation(fn () => ($this->workflow)()->request(CertificateType::Shahadah, $this->convert->id, null, $this->requester), 'convert_id');

        $both = grantRole($this->requester, 'approver', $this->tenant);
        expectRuleViolation(fn () => ($this->workflow)()->approve($certificate, $both), 'status');
        expectRuleViolation(fn () => ($this->workflow)()->issue($certificate, $this->officer), 'status');
    });
});

it('needs a completed enrollment with a passed final exam for a completion certificate', function (): void {
    inTenant($this->tenant, fn () => expectRuleViolation(fn () => ($this->workflow)()->request(CertificateType::GuidanceCompletion, $this->convert->id, null, $this->requester), 'enrollment_id'));
});

it('renders the PDF through Gotenberg into private storage with a checksum', function (): void {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);

    $certificate = ($this->issueShahadah)();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/forms/chromium/convert/html')
        && str_contains((string) $request->body(), 'Daniel Mendoza')
        && str_contains((string) $request->body(), 'شهادة إشهار الإسلام')
        && str_contains((string) $request->body(), 'Certificate of Embracing Islam')
        && str_contains((string) $request->body(), 'on November 1, 2026')
        && substr_count((string) $request->body(), '<svg') === 2);

    $certificate->refresh();
    expect($certificate->file_checksum)->toBe(hash('sha256', '%PDF-1.7 fake'));
    Storage::disk('local')->assertExists($certificate->pdfPath($this->tenant->uuid));
});

it('verifies publicly with the right token only, and shows revoked certificates as revoked', function (): void {
    Queue::fake();
    $token = random_bytes(32);
    $certificate = ($this->issueShahadah)();
    $certificate->forceFill(['verification_token_hash' => hash('sha256', $token, binary: true)])->saveQuietly();
    $url = app(CertificateVerifier::class)->url($certificate, $token);

    $this->get($url)->assertOk()->assertSee($certificate->certificate_number)->assertSee('Daniel Mendoza');
    $this->get(route('certificates.verify', ['uuid' => $certificate->uuid, 't' => 'wrong']))->assertOk()->assertSee(__('certificates.verify.not_found'));

    inTenant($this->tenant, fn () => ($this->workflow)()->revoke($certificate, $this->approver, 'Issued by mistake'));
    $this->get($url)->assertSee(CertificateStatus::Revoked->label());
});

it('keeps an issued snapshot when the convert changes later', function (): void {
    Queue::fake();
    $certificate = ($this->issueShahadah)();

    inTenant($this->tenant, fn () => Convert::query()->findOrFail($this->convert->id)->forceFill(['full_name' => 'Renamed Person'])->save());

    expect($certificate->refresh()->snapshot_data['full_name'])->toBe('Daniel Mendoza')
        ->and(fn () => inTenant($this->tenant, fn () => $certificate->forceFill(['snapshot_data' => ['full_name' => 'x']])->save()))->toThrow(LogicException::class);
});

it('fills templates with escaped snapshot values and executes nothing', function (): void {
    $html = app(TemplateRenderer::class)->render('<p>{{ full_name }}</p>{{ qr_code }}<p>{{ unknown }}</p><?php echo 1; ?>', ['full_name' => '<b>Ali</b>'], '<svg></svg>');

    expect($html)->toBe('<p>&lt;b&gt;Ali&lt;/b&gt;</p><svg></svg><p></p><?php echo 1; ?>');
});

it('versions templates: approved versions are frozen and approval needs a second person', function (): void {
    $author = grantRole(User::factory()->create(), 'central_admin');
    $reviewer = grantRole(User::factory()->create(), 'super_admin');
    $approved = CertificateTemplate::query()->where('certificate_type', CertificateType::Shahadah)->sole();

    expectRuleViolation(fn () => app(ManageTemplates::class)->saveDraft($approved, ['template_code' => $approved->template_code, 'certificate_type' => 'shahadah', 'name' => 'x', 'html_template' => 'x', 'orientation' => 'landscape'], $author), 'html_template');

    $draft = app(ManageTemplates::class)->newVersion($approved, $author);
    expect($draft->template_version)->toBe(2);

    expectRuleViolation(fn () => app(ManageTemplates::class)->approve($draft, $author), 'status');
    app(ManageTemplates::class)->approve($draft, $reviewer);

    expect($approved->refresh()->active)->toBeFalse()
        ->and($draft->refresh()->active)->toBeTrue();
});

it('adds a changed national template as a new version without replacing one approved in Central', function (): void {
    $versions = fn () => CertificateTemplate::query()->whereNull('tenant_id')->where('template_code', 'NATIONAL-SHAHADAH')->orderBy('template_version');

    $this->seed(CertificateTemplateSeeder::class);
    expect($versions()->count())->toBe(1);

    // An older system version (the file has changed since): the next run adds and activates the file.
    $rows = fn () => DB::table('certificate_templates')->whereNull('tenant_id')->where('template_code', 'NATIONAL-SHAHADAH');
    $rows()->update(['html_template' => '<p>old</p>']);
    $this->seed(CertificateTemplateSeeder::class);
    expect($versions()->pluck('active', 'template_version')->all())->toBe([1 => false, 2 => true]);

    // A version approved in Central stays active; the changed file waits as an inactive version.
    $rows()->where('template_version', 2)->update(['active' => false, 'html_template' => '<p>older</p>']);
    $rows()->where('template_version', 1)->update(['active' => true, 'approved_by' => $this->approver->id]);
    $this->seed(CertificateTemplateSeeder::class);

    expect($versions()->pluck('active', 'template_version')->all())->toBe([1 => true, 2 => false, 3 => false]);
});
