<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\VerificationStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Documents\Services\FileInspector;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files attached to records of other modules (dawah materials, later
 * receipts): the same content checks, EXIF removal and signed links as a
 * convert's documents. The calling module authorizes; other modules keep
 * only the returned document id.
 */
final readonly class Attachments
{
    public function __construct(private FileInspector $inspector, private AuditLogger $audit, private TenantContext $tenantContext) {}

    /** Stores the file under $folder and returns the document id. */
    public function store(Model $owner, DocumentType $type, string $folder, string $path, string $originalName, User $actor): int
    {
        $file = $this->inspector->inspect($path);

        return DB::transaction(function () use ($owner, $type, $folder, $file, $originalName, $actor): int {
            $document = new Document;
            $document->uuid = (string) Str::uuid7();
            $document->tenant_id = $this->tenantContext->id();
            $document->owner_type = $owner->getMorphClass();
            $document->owner_id = (int) $owner->getKey();
            $document->document_type = $type;
            $document->original_filename = Str::limit(basename($originalName), 250, '');
            $document->storage_path = trim($folder, '/')."/{$document->uuid}.{$file['extension']}";
            $document->mime_type = $file['mime'];
            $document->file_size = strlen($file['bytes']);
            $document->checksum = hash('sha256', $file['bytes']);
            $document->verification_status = VerificationStatus::Verified;
            $document->uploaded_by = $actor->id;

            Storage::disk((string) config('documents.disk'))->put($document->storage_path, $file['bytes']);
            $document->save();

            $version = new DocumentVersion;
            $version->document_id = $document->id;
            $version->version_no = 1;
            $version->storage_path = $document->storage_path;
            $version->checksum = $document->checksum;
            $version->uploaded_by = $actor->id;
            $version->save();

            $this->audit->record('document.uploaded', $owner, newValues: ['document_id' => $document->id, 'type' => $type->value], actor: $actor);

            return $document->id;
        });
    }

    /** A signed, short-lived download link; the download is audited. */
    public function downloadUrl(Model $owner, int $documentId, User $actor): string
    {
        $document = Document::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->findOrFail($documentId);

        $this->audit->record('document.downloaded', $owner, newValues: ['document_id' => $document->id], actor: $actor);

        return Storage::disk((string) config('documents.disk'))->temporaryUrl(
            $document->storage_path,
            CarbonImmutable::now()->addMinutes((int) config('documents.download_link_minutes')),
            ['ResponseContentDisposition' => 'attachment; filename="'.addslashes($document->original_filename).'"'],
        );
    }
}
