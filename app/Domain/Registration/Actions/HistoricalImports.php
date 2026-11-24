<?php

declare(strict_types=1);

namespace App\Domain\Registration\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Notifications\Contracts\OfficeLinks;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Notifications\Services\OfficeRecipients;
use App\Domain\Registration\Enums\ImportBatchStatus;
use App\Domain\Registration\Enums\ImportRowStatus;
use App\Domain\Registration\Exceptions\ImportRuleViolation;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Jobs\ProcessImportBatch;
use App\Domain\Registration\Models\ImportBatch;
use App\Domain\Registration\Models\ImportRow;
use App\Domain\Registration\Services\DuplicateScreen;
use App\Domain\Registration\Services\HistoricalImportRows;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Import of historical converts (11 §11.5, 15-operations data migration).
 * An office uploads a CSV in the template layout; the file itself is not
 * kept, each row is stored encrypted. Validation checks every row against
 * the office's own data; the import then registers each valid row through
 * RecordShahadah, so every row passes the national duplicate check: an
 * identity already registered anywhere, or a likely match, opens a duplicate
 * review case for the row instead of creating a second convert. Imported
 * rows wait for verification like any registration (shahadah.verify), and
 * duplicate rows can be imported again once the review clears them.
 */
final readonly class HistoricalImports
{
    public function __construct(
        private HistoricalImportRows $rows,
        private RecordShahadah $recordShahadah,
        private IdentityRegistry $identities,
        private DuplicateScreen $duplicates,
        private TenantContext $tenantContext,
        private AuditLogger $audit,
        private OfficeRecipients $recipients,
        private OfficeLinks $links,
    ) {}

    public static function mayImport(User $user): bool
    {
        return $user->hasPermission('converts.import') && $user->hasPermission('shahadah.record');
    }

    /** Stores the rows of the CSV at $path and queues their validation. */
    public function upload(string $path, string $originalFilename, User $actor): ImportBatch
    {
        $this->authorize($actor);
        $tenantId = $this->tenantContext->requireId();
        $records = $this->read($path);

        $batch = DB::transaction(function () use ($records, $originalFilename, $actor, $tenantId): ImportBatch {
            $batch = new ImportBatch;
            $batch->import_type = ImportBatch::TYPE_HISTORICAL_CONVERTS;
            $batch->original_filename = mb_substr(basename($originalFilename), 0, 255);
            $batch->status = ImportBatchStatus::Validating;
            $batch->total_rows = count($records);
            $batch->uploaded_by = $actor->id;
            $batch->save();

            foreach ($records as $rowNumber => $cells) {
                $row = new ImportRow;
                $row->tenant_id = $tenantId;
                $row->import_batch_id = $batch->id;
                $row->row_number = $rowNumber;
                $row->source_data_encrypted = $cells;
                $row->status = ImportRowStatus::Pending;
                $row->save();
            }

            $this->audit->record('import.uploaded', $batch, newValues: ['rows' => $batch->total_rows], actor: $actor);

            return $batch;
        });

        ProcessImportBatch::dispatch($batch->id, $tenantId, ProcessImportBatch::VALIDATE)->onQueue(ProcessImportBatch::QUEUE);

        return $batch;
    }

    /** Runs in ProcessImportBatch, in the batch's office. */
    public function validate(ImportBatch $batch): void
    {
        if ($batch->status !== ImportBatchStatus::Validating) {
            return;
        }

        $seen = [];

        foreach ($batch->rows()->orderBy('row_number')->get() as $row) {
            try {
                $key = $this->rows->identityKey($this->rows->toRegistration($row));

                if (isset($seen[$key])) {
                    throw ImportRuleViolation::repeatedIdentity($seen[$key]);
                }

                $seen[$key] = $row->row_number;
                $this->mark($row, ImportRowStatus::Valid);
            } catch (RuleViolation $violation) {
                $this->mark($row, ImportRowStatus::Invalid, $violation->getMessage());
            }
        }

        $batch->status = ImportBatchStatus::Validated;
        $this->count($batch);
    }

    /** Imports the valid rows, or again the duplicate rows after review. */
    public function start(ImportBatch $batch, User $actor): void
    {
        $this->authorize($actor);

        DB::transaction(function () use ($batch, $actor): void {
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if (! in_array($locked->status, [ImportBatchStatus::Validated, ImportBatchStatus::Completed], true)) {
                throw ImportRuleViolation::wrongStatus();
            }

            if (! $locked->rows()->whereIn('status', [ImportRowStatus::Valid, ImportRowStatus::Duplicate])->exists()) {
                throw ImportRuleViolation::nothingToImport();
            }

            $locked->status = ImportBatchStatus::Importing;
            $locked->started_by = $actor->id;
            $locked->save();

            $this->audit->record('import.started', $locked, actor: $actor);
        });

        ProcessImportBatch::dispatch($batch->id, $batch->tenant_id, ProcessImportBatch::IMPORT)->onQueue(ProcessImportBatch::QUEUE);
    }

    /** Runs in ProcessImportBatch, in the batch's office, as the user who started it. */
    public function import(ImportBatch $batch): void
    {
        if ($batch->status !== ImportBatchStatus::Importing || $batch->started_by === null) {
            return;
        }

        $actor = User::query()->findOrFail($batch->started_by);
        $imported = 0;

        foreach ($batch->rows()->whereIn('status', [ImportRowStatus::Valid, ImportRowStatus::Duplicate])->orderBy('row_number')->get() as $row) {
            if ($this->importRow($row, $actor)) {
                $imported++;
            }
        }

        $batch->status = ImportBatchStatus::Completed;
        $batch->completed_at = CarbonImmutable::now();
        $this->count($batch);

        $this->audit->record('import.completed', $batch, newValues: [
            'imported' => $imported,
            'success_rows' => $batch->success_rows,
            'failed_rows' => $batch->failed_rows,
            'duplicate_rows' => $batch->duplicate_rows,
        ], actor: $actor);

        $this->announce($batch, $actor, $imported);
    }

    public function cancel(ImportBatch $batch, User $actor): void
    {
        $this->authorize($actor);

        DB::transaction(function () use ($batch, $actor): void {
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($locked->status !== ImportBatchStatus::Validated) {
                throw ImportRuleViolation::wrongStatus();
            }

            $locked->rows()->whereIn('status', [ImportRowStatus::Pending, ImportRowStatus::Valid])->update(['status' => ImportRowStatus::Skipped]);
            $locked->status = ImportBatchStatus::Cancelled;
            $locked->save();

            $this->audit->record('import.cancelled', $locked, actor: $actor);
        });
    }

    /** Marks a batch whose job failed, so the office sees it. */
    public function fail(ImportBatch $batch, Throwable $exception): void
    {
        $batch->status = ImportBatchStatus::Failed;
        $batch->error_summary = mb_substr($exception->getMessage(), 0, 500);
        $batch->save();
    }

    private function importRow(ImportRow $row, User $actor): bool
    {
        try {
            $data = $this->rows->toRegistration($row);
        } catch (RuleViolation $violation) {
            $this->mark($row, ImportRowStatus::Invalid, $violation->getMessage());

            return false;
        }

        $existing = $this->identities->findActive($data->identity);

        if ($existing !== null) {
            $this->duplicates->openForIdentity($data, $existing->convert_id, $actor);
            $this->mark($row, ImportRowStatus::Duplicate, __('imports.errors.identity_registered'));

            return false;
        }

        try {
            $registration = $this->recordShahadah->handle($data, $actor);
        } catch (RegistrationRuleViolation $violation) {
            // possibleDuplicate (name) or alreadyRegistered (identity, a race with another registration).
            $duplicate = in_array($violation->field, ['full_name', 'identity_number'], true);
            $this->mark($row, $duplicate ? ImportRowStatus::Duplicate : ImportRowStatus::Invalid, $violation->getMessage());

            return false;
        } catch (RuleViolation $violation) {
            $this->mark($row, ImportRowStatus::Invalid, $violation->getMessage());

            return false;
        }

        $row->resulting_record_type = 'convert_registration';
        $row->resulting_record_id = $registration->id;
        $this->mark($row, ImportRowStatus::Imported);

        return true;
    }

    private function mark(ImportRow $row, ImportRowStatus $status, ?string $message = null): void
    {
        $row->status = $status;
        $row->error_message = $message === null ? null : mb_substr($message, 0, 500);
        $row->save();
    }

    private function count(ImportBatch $batch): void
    {
        $counts = $batch->rows()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $batch->success_rows = (int) ($counts[ImportRowStatus::Imported->value] ?? 0);
        $batch->failed_rows = (int) ($counts[ImportRowStatus::Invalid->value] ?? 0);
        $batch->duplicate_rows = (int) ($counts[ImportRowStatus::Duplicate->value] ?? 0);
        $batch->save();
    }

    /** One alert to the verifiers and one to the importer, instead of one per row. */
    private function announce(ImportBatch $batch, User $actor, int $imported): void
    {
        $tenant = Tenant::query()->findOrFail($batch->tenant_id);

        if ($imported > 0) {
            $verifiers = $this->recipients->withPermission($tenant->id, 'shahadah.verify');

            if ($verifiers->isNotEmpty()) {
                Notification::send($verifiers, new OfficeAlert('import_awaiting_verification', $tenant->id, ['count' => $imported], $this->links->shahadahRegistrations($tenant)));
            }
        }

        $actor->notify(new OfficeAlert('import_completed', $tenant->id, [
            'imported' => $batch->success_rows,
            'duplicates' => $batch->duplicate_rows,
            'invalid' => $batch->failed_rows,
        ], $this->links->importBatch($tenant, $batch->id)));
    }

    /**
     * Rows of the CSV keyed by their line number in the spreadsheet (the
     * header is line 1). A UTF-8 BOM, blank lines and unknown columns are
     * ignored.
     *
     * @return array<int, array<string, string>>
     */
    private function read(string $path): array
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw ImportRuleViolation::unreadableFile();
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if (! is_array($header)) {
                throw ImportRuleViolation::noRows();
            }

            $header = array_map(fn (?string $cell): string => mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell) ?? '')), $header);
            $missing = array_diff(HistoricalImportRows::requiredColumns(), $header);

            if ($missing !== []) {
                throw ImportRuleViolation::missingColumns(implode(', ', $missing));
            }

            $maximum = (int) config('registration.import_max_rows');
            $records = [];
            $line = 1;

            while (($cells = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                if ($cells === [null] || implode('', array_map(fn (?string $cell): string => trim((string) $cell), $cells)) === '') {
                    continue;
                }

                if (count($records) >= $maximum) {
                    throw ImportRuleViolation::tooManyRows($maximum);
                }

                $record = [];

                foreach ($header as $index => $column) {
                    if (array_key_exists($column, HistoricalImportRows::COLUMNS)) {
                        $record[$column] = trim((string) ($cells[$index] ?? ''));
                    }
                }

                $records[$line] = $record;
            }
        } finally {
            fclose($handle);
        }

        if ($records === []) {
            throw ImportRuleViolation::noRows();
        }

        return $records;
    }

    private function authorize(User $actor): void
    {
        if (! self::mayImport($actor)) {
            throw new AuthorizationException;
        }
    }
}
