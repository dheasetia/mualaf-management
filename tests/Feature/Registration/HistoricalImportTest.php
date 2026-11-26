<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\HistoricalImports;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Actions\ResolveDuplicateCase;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Enums\CandidateType;
use App\Domain\Registration\Enums\ImportBatchStatus;
use App\Domain\Registration\Enums\ImportRowStatus;
use App\Domain\Registration\Enums\MatchReason;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Enums\ReviewDecision;
use App\Domain\Registration\Events\ShahadahRecorded;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Domain\Registration\Models\ImportBatch;
use App\Domain\Registration\Models\ImportRow;
use App\Domain\Registration\Services\HistoricalImportRows;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Center\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Center\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Filament\Center\Resources\ImportBatches\RelationManagers\RowsRelationManager;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->philippines = Country::factory()->create(['iso2' => 'PH', 'iso3' => 'PHL']);
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->registrar = grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam);
    $this->head = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    $this->verifier = grantRole(memberOf($this->dammam), 'shahadah_verifier', $this->dammam);
    inTenant($this->dammam, fn () => Dai::factory()->create(['employee_number' => 'EMP-001']));

    $this->row = fn (array $overrides = []): array => [...HistoricalImportRows::EXAMPLE, 'identity_number' => saudiId('212345678'), 'date_of_birth' => '', ...$overrides];

    $this->csv = function (array $rows, ?array $header = null): string {
        $path = tempnam(sys_get_temp_dir(), 'import');
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header ?? array_keys(HistoricalImportRows::COLUMNS), escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), escape: '');
        }

        fclose($handle);

        return $path;
    };

    $this->upload = fn (array $rows, ?User $actor = null): ImportBatch => inTenant($this->dammam,
        fn () => app(HistoricalImports::class)->upload(($this->csv)($rows), 'converts.csv', $actor ?? $this->registrar)->refresh());

    $this->start = function (ImportBatch $batch): ImportBatch {
        inTenant($this->dammam, fn () => app(HistoricalImports::class)->start($batch->refresh(), $this->registrar));

        return inTenant($this->dammam, fn () => $batch->refresh());
    };

    $this->rows = fn (ImportBatch $batch) => inTenant($this->dammam, fn () => ImportRow::query()->where('import_batch_id', $batch->id)->orderBy('row_number')->get());

    // Registered earlier in Riyadh.
    $this->existing = inTenant($this->riyadh, function (): Convert {
        $registration = app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
            profile: new ConvertProfileData('Maria Dela Cruz', Gender::Female, $this->philippines->id, dateOfBirth: CarbonImmutable::parse('1985-02-10')),
            identity: new IdentifierData(IdentityType::Iqamah, saudiId('298765432')),
            shahadahDate: CarbonImmutable::today()->subYears(2),
            location: 'Riyadh center',
            primaryDaiId: Dai::factory()->create()->id,
            witnesses: shahadahWitnesses(),
        ), grantRole(memberOf($this->riyadh), 'registration_officer', $this->riyadh));

        return Convert::query()->findOrFail($registration->convert_id);
    });
});

it('checks every row on upload without keeping the file in the clear', function (): void {
    $batch = ($this->upload)([
        ($this->row)(),
        ($this->row)(['identity_number' => saudiId('223456789'), 'full_name' => 'Ana Lopez', 'gender' => 'female', 'nationality' => 'XX']),
        ($this->row)(['identity_number' => saudiId('234567891'), 'full_name' => 'Ben Cruz', 'shahadah_date' => '08/03/2019']),
        ($this->row)(['full_name' => 'Same Iqamah Again']),
        ($this->row)(['identity_number' => saudiId('245678912'), 'full_name' => 'One Witness', 'witness_2_name' => '', 'witness_2_phone' => '']),
        ($this->row)(['identity_number' => saudiId('256789123'), 'full_name' => 'Unknown Dai', 'primary_dai' => 'EMP-999']),
    ]);

    $rows = ($this->rows)($batch);

    expect($batch)
        ->status->toBe(ImportBatchStatus::Validated)
        ->total_rows->toBe(6)
        ->failed_rows->toBe(5)
        ->and($rows->pluck('row_number')->all())->toBe([2, 3, 4, 5, 6, 7])
        ->and($rows->pluck('status')->all())->toBe([ImportRowStatus::Valid, ...array_fill(0, 5, ImportRowStatus::Invalid)])
        ->and($rows[1]->error_message)->toContain('nationality')
        ->and($rows[2]->error_message)->toContain('shahadah_date')
        ->and($rows[3]->error_message)->toContain('2')
        ->and($rows[5]->error_message)->toContain('primary_dai')
        ->and($rows[0]->getRawOriginal('source_data_encrypted'))->not->toContain('Jose Santos')
        ->and(inTenant($this->dammam, fn () => Convert::query()->count()))->toBe(0);
});

it('rejects a file without the template columns', function (): void {
    $path = ($this->csv)([['Jose Santos', 'male']], ['full_name', 'gender']);

    expectRuleViolation(fn () => inTenant($this->dammam, fn () => app(HistoricalImports::class)->upload($path, 'converts.csv', $this->registrar)), 'file');
    expect(inTenant($this->dammam, fn () => ImportBatch::query()->count()))->toBe(0);
});

it('lets only holders of converts.import with shahadah.record import', function (): void {
    expect(fn () => ($this->upload)([($this->row)()], $this->head))->toThrow(AuthorizationException::class);
});

it('registers valid rows as historical shahadahs waiting for verification, announced once', function (): void {
    Notification::fake();
    Event::fake([ShahadahRecorded::class]);
    $batch = ($this->upload)([
        ($this->row)(),
        ($this->row)(['identity_number' => saudiId('267891234'), 'full_name' => 'Pedro Garcia', 'shahadah_date' => '2018-11-02', 'phone' => '0559876543']),
        ($this->row)(['identity_number' => '', 'full_name' => 'No Identity']),
    ]);

    $batch = ($this->start)($batch);
    $rows = ($this->rows)($batch);

    $registrations = inTenant($this->dammam, fn () => ConvertRegistration::query()->with('shahadah')->orderBy('id')->get());

    expect($batch)
        ->status->toBe(ImportBatchStatus::Completed)
        ->success_rows->toBe(2)
        ->failed_rows->toBe(1)
        ->started_by->toBe($this->registrar->id)
        ->and($registrations)->toHaveCount(2)
        ->and($registrations->pluck('status')->unique()->all())->toBe([RegistrationStatus::PendingVerification])
        ->and($registrations[1]->shahadah->shahadah_date->toDateString())->toBe('2018-11-02')
        ->and($rows[1]->resulting_record_id)->toBe($registrations[1]->id)
        ->and(AuditLog::query()->where('action', 'import.completed')->exists())->toBeTrue();

    Event::assertNotDispatched(ShahadahRecorded::class);
    Notification::assertSentTo($this->verifier, OfficeAlert::class, fn (OfficeAlert $alert): bool => $alert->kind === 'import_awaiting_verification' && $alert->parameters['count'] === 2);
    Notification::assertSentTo($this->registrar, OfficeAlert::class, fn (OfficeAlert $alert): bool => $alert->kind === 'import_completed');
});

it('sends an identity registered in another office to duplicate review instead of registering it twice', function (): void {
    $batch = ($this->start)(($this->upload)([
        ($this->row)(['identity_number' => saudiId('298765432'), 'full_name' => 'Maria Cruz', 'gender' => 'female']),
    ]));

    $row = ($this->rows)($batch)->sole();
    $case = inTenant($this->dammam, fn () => DuplicateReviewCase::query()->sole());

    expect($row->status)->toBe(ImportRowStatus::Duplicate)
        ->and($batch->duplicate_rows)->toBe(1)
        ->and($case)
        ->candidate_type->toBe(CandidateType::ImportRow)
        ->candidate_id->toBe($row->id)
        ->match_reason->toBe(MatchReason::ExactIdentity)
        ->possible_convert_id->toBe($this->existing->id)
        ->and(inTenant($this->dammam, fn () => Convert::query()->count()))->toBe(0);

    // Importing again does not open a second case.
    ($this->start)($batch);
    expect(inTenant($this->dammam, fn () => DuplicateReviewCase::query()->count()))->toBe(1);
});

it('imports a likely duplicate only after the review finds a different person', function (): void {
    $batch = ($this->start)(($this->upload)([
        ($this->row)(['full_name' => 'Maria Dela Cruz', 'gender' => 'female', 'date_of_birth' => '1985-02-10']),
    ]));

    $case = inTenant($this->dammam, fn () => DuplicateReviewCase::query()->sole());
    expect(($this->rows)($batch)->sole()->status)->toBe(ImportRowStatus::Duplicate)
        ->and($case->match_reason)->toBe(MatchReason::FuzzyNameDob)
        ->and($case->candidate_type)->toBe(CandidateType::ImportRow);

    inTenant($this->dammam, fn () => app(ResolveDuplicateCase::class)->handle($case, ReviewDecision::DifferentPerson, $this->head, 'Different mother name'));
    $batch = ($this->start)($batch);

    expect(($this->rows)($batch)->sole()->status)->toBe(ImportRowStatus::Imported)
        ->and($batch->success_rows)->toBe(1)
        ->and($batch->duplicate_rows)->toBe(0);
});

it('does not import a cancelled batch', function (): void {
    $batch = ($this->upload)([($this->row)()]);
    inTenant($this->dammam, fn () => app(HistoricalImports::class)->cancel($batch, $this->registrar));

    expectRuleViolation(fn () => ($this->start)($batch), 'batch');
    expect(($this->rows)($batch)->sole()->status)->toBe(ImportRowStatus::Skipped);
});

it('shows imports to the importer only', function (): void {
    $batch = ($this->upload)([($this->row)()]);

    $this->actingAs($this->registrar)->get(ImportBatchResource::getUrl('index', panel: 'center', tenant: $this->dammam))->assertOk();
    $this->actingAs($this->registrar)->get(ImportBatchResource::getUrl('view', ['record' => $batch], panel: 'center', tenant: $this->dammam))
        ->assertOk();
    $this->actingAs($this->head)->get(ImportBatchResource::getUrl('index', panel: 'center', tenant: $this->dammam))->assertForbidden();

    inCenterPanel($this->dammam, $this->registrar);
    Livewire::test(RowsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewImportBatch::class])
        ->assertOk()->assertSee('Jose Santos');
});

it('uploads a CSV from the import page', function (): void {
    inCenterPanel($this->dammam, $this->registrar);
    $file = UploadedFile::fake()->createWithContent('converts.csv', (string) file_get_contents(($this->csv)([($this->row)()])));

    Livewire::test(ListImportBatches::class)
        ->callAction('upload', ['file' => $file])
        ->assertHasNoActionErrors();

    expect(ImportBatch::query()->sole())
        ->status->toBe(ImportBatchStatus::Validated)
        ->original_filename->toBe('converts.csv');
});
