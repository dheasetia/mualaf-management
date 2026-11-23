<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Jobs\ScanDocument;
use App\Domain\Documents\Models\Document;

/**
 * Antivirus gate for stored files (13 §13.1): new files start pending when
 * scanning is on (else skipped); only safe files can be verified or
 * downloaded. Other modules ask by document id.
 */
final class FileSafety
{
    public function initialStatus(): ScanStatus
    {
        return (bool) config('documents.antivirus.enabled') ? ScanStatus::Pending : ScanStatus::Skipped;
    }

    public function queueScan(Document $document): void
    {
        if ($document->scan_status === ScanStatus::Pending) {
            ScanDocument::dispatch($document->id)->onQueue(ScanDocument::QUEUE)->afterCommit();
        }
    }

    public function isSafe(?int $documentId): bool
    {
        $status = $documentId === null ? null : Document::query()->whereKey($documentId)->value('scan_status');

        return $status instanceof ScanStatus ? $status->isSafe() : ScanStatus::tryFrom((string) $status)?->isSafe() ?? false;
    }
}
