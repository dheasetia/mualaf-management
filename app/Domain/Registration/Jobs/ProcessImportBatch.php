<?php

declare(strict_types=1);

namespace App\Domain\Registration\Jobs;

use App\Domain\Registration\Actions\HistoricalImports;
use App\Domain\Registration\Models\ImportBatch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Validates or imports a batch on the `imports` queue, in the batch's office.
 * Carries ids only; each step checks the batch status first, so a retry or
 * a second dispatch does nothing.
 */
final class ProcessImportBatch implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'imports';

    public const string VALIDATE = 'validate';

    public const string IMPORT = 'import';

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $batchId, public readonly int $tenantId, public readonly string $step) {}

    public function handle(TenantContext $tenantContext, HistoricalImports $imports): void
    {
        $tenantContext->run($this->tenantId, function () use ($imports): void {
            $batch = ImportBatch::query()->findOrFail($this->batchId);

            try {
                $this->step === self::IMPORT ? $imports->import($batch) : $imports->validate($batch);
            } catch (Throwable $exception) {
                $imports->fail($batch, $exception);
                report($exception);
            }
        });
    }
}
