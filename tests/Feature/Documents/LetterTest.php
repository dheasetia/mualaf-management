<?php

declare(strict_types=1);

use App\Domain\Documents\Actions\LetterWorkflow;
use App\Domain\Documents\Actions\ManageDocumentTemplates;
use App\Domain\Documents\Enums\GeneratedStatus;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Jobs\GenerateLetterPdf;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    seedRbac();
    $this->seed(DocumentTemplateSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    Queue::fake();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->officer = grantRole(memberOf($this->tenant), 'administrative_officer', $this->tenant);
    $this->generate = fn ($convert, LetterType $type, ?User $actor = null) => inTenant($this->tenant, fn () => app(LetterWorkflow::class)
        ->generate($convert, $type, ['addressee' => 'King Fahd Hospital', 'body' => null], $actor ?? $this->officer));
});

it('issues a numbered letter from the active template and queues its PDF', function (): void {
    $letter = ($this->generate)(documentsConvert($this->tenant), LetterType::NewConvertDataLetter);

    expect($letter)
        ->status->toBe(GeneratedStatus::Issued)
        ->document_number->toBe('DMM-DOC-'.now()->format('Y').'-000001')
        ->template_id->toBe(DocumentTemplate::query()->where('document_type', LetterType::NewConvertDataLetter)->sole()->id)
        ->and($letter->snapshot_data)->toMatchArray(['full_name' => 'Daniel Mendoza', 'addressee' => 'King Fahd Hospital', 'shahadah_date' => '2026-11-01']);

    Queue::assertPushedOn('pdf', GenerateLetterPdf::class);
});

it('refuses letters before the shahadah is verified, certificates as letters, and circumcision referrals for women', function (): void {
    expectRuleViolation(fn () => ($this->generate)(documentsConvert($this->tenant, verified: false), LetterType::ReligionUpdateLetter), 'document_type');
    expectRuleViolation(fn () => ($this->generate)(documentsConvert($this->tenant, identity: '277001133'), LetterType::ShahadahCertificate), 'document_type');
    expectRuleViolation(fn () => ($this->generate)(documentsConvert($this->tenant, Gender::Female, identity: '277001144'), LetterType::KhitanReferralLetter), 'document_type');
});

it('keeps the khitan referral to a request for medical assessment under hospital policy', function (): void {
    $template = DocumentTemplate::query()->where('document_type', LetterType::KhitanReferralLetter)->sole();

    expect($template->html_template)->toContain('الفحص الطبي')->toContain('سياسة المستشفى');
});

it('lets only documents.generate holders issue letters and cancels issued letters with a reason', function (): void {
    $convert = documentsConvert($this->tenant);
    expect(fn () => ($this->generate)($convert, LetterType::OtherLetter, grantRole(memberOf($this->tenant), 'registration_officer', $this->tenant)))
        ->toThrow(AuthorizationException::class);

    $letter = ($this->generate)($convert, LetterType::OtherLetter);

    inTenant($this->tenant, function () use ($letter): void {
        expectRuleViolation(fn () => app(LetterWorkflow::class)->cancel($letter, $this->officer, ''), 'reason');
        app(LetterWorkflow::class)->cancel($letter, $this->officer, 'Wrong addressee');
        expectRuleViolation(fn () => app(LetterWorkflow::class)->cancel($letter, $this->officer, 'Again'), 'status');
    });

    expect($letter->refresh()->status)->toBe(GeneratedStatus::Cancelled);
});

it('versions letter templates with approval by a second person', function (): void {
    $author = grantRole(User::factory()->create(), 'central_admin');
    $approved = DocumentTemplate::query()->where('document_type', LetterType::OtherLetter)->sole();

    expectRuleViolation(fn () => app(ManageDocumentTemplates::class)->saveDraft($approved, [
        'template_code' => $approved->template_code, 'document_type' => 'other_letter', 'name_ar' => 'x', 'html_template' => 'x', 'orientation' => 'portrait',
    ], $author), 'html_template');

    $draft = app(ManageDocumentTemplates::class)->newVersion($approved, $author);
    expectRuleViolation(fn () => app(ManageDocumentTemplates::class)->approve($draft, $author), 'status');
    app(ManageDocumentTemplates::class)->approve($draft, grantRole(User::factory()->create(), 'super_admin'));

    expect($draft->refresh())->version->toBe(2)->active->toBeTrue()
        ->and($approved->refresh()->active)->toBeFalse();
});
