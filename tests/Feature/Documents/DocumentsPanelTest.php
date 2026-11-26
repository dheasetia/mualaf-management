<?php

declare(strict_types=1);

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Converts\Pages\ViewConvert;
use App\Filament\Center\Resources\Converts\RelationManagers\DocumentsRelationManager;
use App\Filament\Center\Resources\Converts\RelationManagers\LettersRelationManager;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->seed(DocumentTemplateSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    Storage::fake('local');
    Queue::fake();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->convert = documentsConvert($this->tenant);
    $this->staff = fn (string $role): User => grantRole(memberOf($this->tenant), $role, $this->tenant);
    $this->open = fn (string $manager) => Livewire::test($manager, ['ownerRecord' => $this->convert, 'pageClass' => ViewConvert::class]);
});

it('uploads a file on the convert page', function (): void {
    inCenterPanel($this->tenant, ($this->staff)('registration_officer'));

    ($this->open)(DocumentsRelationManager::class)
        ->callAction(TestAction::make('upload')->table(), [
            'document_type' => 'iqamah_copy',
            'file' => UploadedFile::fake()->createWithContent('iqamah.pdf', "%PDF-1.4\n%fake\n"),
        ])
        ->assertHasNoActionErrors();

    expect(Document::query()->sole())->original_filename->toBe('iqamah.pdf')->owner_id->toBe($this->convert->id);
});

it('reports a file whose content is not an allowed type', function (): void {
    inCenterPanel($this->tenant, ($this->staff)('registration_officer'));

    ($this->open)(DocumentsRelationManager::class)
        ->callAction(TestAction::make('upload')->table(), [
            'document_type' => 'photo',
            'file' => UploadedFile::fake()->createWithContent('photo.png', 'not really an image'),
        ])
        ->assertNotified(__('documents.errors.file_type'));

    expect(Document::query()->count())->toBe(0);
});

it('generates a letter on the convert page and hides the action without documents.generate', function (): void {
    inCenterPanel($this->tenant, ($this->staff)('administrative_officer'));
    ($this->open)(LettersRelationManager::class)
        ->callAction(TestAction::make('generate')->table(), ['document_type' => 'religion_update_letter', 'addressee' => 'Civil registry'])
        ->assertHasNoActionErrors();
    expect(inTenant($this->tenant, fn () => GeneratedDocument::query()->sole())->snapshot_data['addressee'])->toBe('Civil registry');

    inCenterPanel($this->tenant, ($this->staff)('registration_officer'));
    ($this->open)(LettersRelationManager::class)->assertActionHidden(TestAction::make('generate')->table());
});

it('opens the national letter templates in Central to template managers only', function (): void {
    $this->actingAs(grantRole(User::factory()->create(), 'central_admin'))->get('/central/document-templates')->assertOk();
    $this->actingAs(grantRole(User::factory()->create(), 'central_auditor'))->get('/central/document-templates')->assertForbidden();
});
