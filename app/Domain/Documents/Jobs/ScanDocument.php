<?php

declare(strict_types=1);

namespace App\Domain\Documents\Jobs;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Enums\VerificationStatus;
use App\Domain\Documents\Models\Document;
use App\Support\Antivirus\FileScanner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Scans an uploaded file (queue `files`, 13 §13.3). An infected file is
 * deleted from storage and the document rejected; a scanner outage is
 * retried and finally marked failed, which keeps the file unusable.
 */
final class ScanDocument implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'files';

    public int $tries = 5;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $documentId) {}

    public function handle(FileScanner $scanner, AuditLogger $audit): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null || $document->scan_status !== ScanStatus::Pending) {
            return;
        }

        $disk = Storage::disk((string) config('documents.disk'));

        try {
            $signature = $scanner->scan((string) $disk->get($document->storage_path));
        } catch (RuntimeException $unreachable) {
            // Scanner outage: try again later; the file stays pending (unusable) meanwhile.
            $this->attempts() >= $this->tries ? $this->failed($unreachable) : $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)] ?? 60);

            return;
        }

        $document->scan_status = $signature === null ? ScanStatus::Clean : ScanStatus::Infected;
        $document->scanned_at = CarbonImmutable::now();

        if ($signature !== null) {
            $disk->delete($document->storage_path);
            $document->verification_status = VerificationStatus::Rejected;
            $document->rejection_reason = 'malware: '.$signature;
            $audit->record('document.infected', $document, newValues: ['signature' => $signature], tenantId: $document->tenant_id);
        }

        $document->save();
    }

    public function failed(?\Throwable $exception): void
    {
        Document::query()->whereKey($this->documentId)->where('scan_status', ScanStatus::Pending)
            ->update(['scan_status' => ScanStatus::Failed, 'scanned_at' => CarbonImmutable::now()]);

        if ($exception !== null && ! $exception instanceof RuntimeException) {
            report($exception);
        }
    }
}
