<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Documents\Actions\LetterWorkflow;
use App\Domain\Documents\Actions\ManageDocumentTemplates;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * Critical test #10 (14-testing): an issued document stays the same even when
 * its source data and its template change.
 */
beforeEach(function (): void {
    seedRbac();
    $this->seed(DocumentTemplateSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->convert = documentsConvert($this->tenant);
    $this->letter = inTenant($this->tenant, fn () => app(LetterWorkflow::class)->generate(
        $this->convert, LetterType::NewConvertDataLetter, ['addressee' => 'Passport office'],
        grantRole(memberOf($this->tenant), 'administrative_officer', $this->tenant),
    ));
});

it('keeps an issued letter\'s snapshot and template when the convert and the template change', function (): void {
    $original = $this->letter->template_id;

    inTenant($this->tenant, fn () => Convert::query()->findOrFail($this->convert->id)->forceFill(['full_name' => 'Renamed Person'])->save());
    $templates = app(ManageDocumentTemplates::class);
    $draft = $templates->newVersion(DocumentTemplate::query()->findOrFail($original), grantRole(User::factory()->create(), 'central_admin'));
    $draft = $templates->saveDraft($draft, [
        'template_code' => $draft->template_code, 'document_type' => 'new_convert_data_letter', 'name_ar' => 'v2',
        'html_template' => '<p>NEW TEMPLATE {{ full_name }}</p>', 'orientation' => 'portrait',
    ], grantRole(User::factory()->create(), 'central_admin'));
    $templates->approve($draft, grantRole(User::factory()->create(), 'super_admin'));

    $letter = inTenant($this->tenant, fn () => $this->letter->refresh());
    expect($letter->template_id)->toBe($original)
        ->and($letter->snapshot_data['full_name'])->toBe('Daniel Mendoza');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->body(), 'Daniel Mendoza')
        && ! str_contains((string) $request->body(), 'NEW TEMPLATE'));
});

it('refuses to change an issued letter or an approved template in place', function (): void {
    inTenant($this->tenant, function (): void {
        expect(fn () => $this->letter->forceFill(['snapshot_data' => ['full_name' => 'x']])->save())->toThrow(LogicException::class)
            ->and(fn () => $this->letter->forceFill(['template_id' => $this->letter->template_id + 1])->save())->toThrow(LogicException::class);
    });

    expect(fn () => DocumentTemplate::query()->findOrFail($this->letter->template_id)->forceFill(['html_template' => 'changed'])->save())
        ->toThrow(LogicException::class);
});

it('renders the PDF from the snapshot into private storage with a checksum', function (): void {
    $letter = inTenant($this->tenant, fn () => $this->letter->refresh());

    expect($letter->file_checksum)->toBe(hash('sha256', '%PDF-1.7 fake'));
    Storage::disk('local')->assertExists($letter->pdfPath($this->tenant->uuid));
});
