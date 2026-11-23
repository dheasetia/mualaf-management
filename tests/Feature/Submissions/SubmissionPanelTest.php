<?php

declare(strict_types=1);

use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Models\SubmissionType;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Center\Resources\Submissions\Pages\ViewSubmission;
use App\Filament\Center\Resources\Submissions\RelationManagers\DocumentsRelationManager;
use Database\Seeders\SubmissionTypeSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->seed(SubmissionTypeSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    Storage::fake('local');
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->clerk = withAuthenticator(grantRole(memberOf($this->tenant), 'administrative_officer', $this->tenant));
    $this->convert = documentsConvert($this->tenant);
});

it('creates a submission, attaches the required file and submits it from the panel', function (): void {
    inCenterPanel($this->tenant, $this->clerk);

    Livewire::test(ListSubmissions::class)
        ->callAction('create', ['convert_id' => $this->convert->id, 'submission_type_id' => SubmissionType::query()->where('code', 'other_letter')->sole()->id])
        ->assertHasNoActionErrors();
    $submission = inTenant($this->tenant, fn () => Submission::query()->sole());

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $submission, 'pageClass' => ViewSubmission::class])
        ->callAction(TestAction::make('attach')->table(), [
            'requirement_code' => 'iqamah_copy',
            'file' => UploadedFile::fake()->createWithContent('iqamah.pdf', "%PDF-1.4\n%fake\n"),
        ])
        ->assertHasNoActionErrors();

    Livewire::test(ViewSubmission::class, ['record' => $submission->getRouteKey()])
        ->assertActionHidden('approve')
        ->callAction('submit');

    expect(inTenant($this->tenant, fn () => $submission->refresh()->status))->toBe(SubmissionStatus::Submitted);
});

it('shows submissions to submissions.view holders only', function (): void {
    $this->actingAs(withAuthenticator(grantRole(memberOf($this->tenant), 'approver', $this->tenant)))->get('/center/DMM/submissions')->assertOk();
    $this->actingAs(grantRole(memberOf($this->tenant), 'teacher', $this->tenant))->get('/center/DMM/submissions')->assertForbidden();
});
