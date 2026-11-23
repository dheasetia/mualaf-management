<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\VerificationStatus;
use App\Domain\Documents\Exceptions\DocumentRuleViolation;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Documents\Services\FileInspector;
use App\Domain\Documents\Services\FileSafety;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files of a convert (09 §9.7, 13 §13.1). Every operation goes through the
 * convert's policy, so files follow the convert after a transfer while
 * staying in the folder of the office that uploaded them. Files are never
 * overwritten: a replacement is a new version.
 */
final readonly class DocumentLibrary
{
    public function __construct(private FileInspector $inspector, private FileSafety $safety, private AuditLogger $audit, private TenantContext $tenantContext) {}

    public function upload(Convert $convert, DocumentType $type, string $path, string $originalName, User $actor): Document
    {
        $this->authorize($actor, 'uploadDocuments', $convert);
        $file = $this->inspector->inspect($path);

        return DB::transaction(function () use ($convert, $type, $file, $originalName, $actor): Document {
            $document = new Document;
            $document->uuid = (string) Str::uuid7();
            $document->tenant_id = $this->tenantContext->requireId();
            $document->owner_type = $convert->getMorphClass();
            $document->owner_id = $convert->id;
            $document->document_type = $type;
            $document->uploaded_by = $actor->id;
            $document->verification_status = VerificationStatus::Pending;
            $this->store($document, $convert, $file, $originalName, 1);
            $document->scan_status = $this->safety->initialStatus();
            $document->save();
            $this->safety->queueScan($document);

            $this->version($document, $actor);
            $this->audit->record('document.uploaded', $convert, newValues: ['document_id' => $document->id, 'type' => $type->value], actor: $actor);

            return $document;
        });
    }

    /** A replacement file: the next version, verification starts again. */
    public function addVersion(Document $document, string $path, string $originalName, User $actor): Document
    {
        $convert = $this->owner($document);
        $this->authorize($actor, 'uploadDocuments', $convert);
        $file = $this->inspector->inspect($path);

        return DB::transaction(function () use ($document, $convert, $file, $originalName, $actor): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $this->store($locked, $convert, $file, $originalName, $locked->current_version + 1);
            $locked->verification_status = VerificationStatus::Pending;
            $locked->verified_by = null;
            $locked->verified_at = null;
            $locked->rejection_reason = null;
            $locked->scan_status = $this->safety->initialStatus();
            $locked->scanned_at = null;
            $locked->save();
            $this->safety->queueScan($locked);

            $this->version($locked, $actor);
            $this->audit->record('document.version_added', $convert, newValues: ['document_id' => $locked->id, 'version' => $locked->current_version], actor: $actor);

            return $locked;
        });
    }

    public function verify(Document $document, User $actor): Document
    {
        return $this->decide($document, $actor, VerificationStatus::Verified, null);
    }

    public function reject(Document $document, User $actor, ?string $reason): Document
    {
        if (blank($reason)) {
            throw DocumentRuleViolation::reasonRequired();
        }

        return $this->decide($document, $actor, VerificationStatus::Rejected, trim((string) $reason));
    }

    /** A signed link valid for a few minutes; every download is audited. */
    public function downloadUrl(Document $document, User $actor): string
    {
        $convert = $this->owner($document);
        $this->authorize($actor, 'viewDocuments', $convert);

        if (! $document->scan_status->isSafe()) {
            throw DocumentRuleViolation::notScanned();
        }

        $this->audit->record('document.downloaded', $convert, newValues: ['document_id' => $document->id, 'version' => $document->current_version], actor: $actor);

        return Storage::disk((string) config('documents.disk'))->temporaryUrl(
            $document->storage_path,
            CarbonImmutable::now()->addMinutes((int) config('documents.download_link_minutes')),
            ['ResponseContentDisposition' => 'attachment; filename="'.addslashes($document->original_filename).'"'],
        );
    }

    /**
     * Files of a convert, newest first.
     *
     * @return Builder<Document>
     */
    public function of(Convert $convert): Builder
    {
        return Document::query()->where('owner_type', $convert->getMorphClass())->where('owner_id', $convert->id)->latest('id');
    }

    private function decide(Document $document, User $actor, VerificationStatus $target, ?string $reason): Document
    {
        $convert = $this->owner($document);
        $this->authorize($actor, 'verifyDocuments', $convert);

        return DB::transaction(function () use ($document, $convert, $actor, $target, $reason): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);

            if ($locked->verification_status !== VerificationStatus::Pending) {
                throw DocumentRuleViolation::alreadyDecided();
            }

            if ($target === VerificationStatus::Verified && ! $locked->scan_status->isSafe()) {
                throw DocumentRuleViolation::notScanned();
            }

            if ($locked->uploaded_by === $actor->id) {
                throw DocumentRuleViolation::ownUpload();
            }

            $locked->verification_status = $target;
            $locked->verified_by = $actor->id;
            $locked->verified_at = CarbonImmutable::now();
            $locked->rejection_reason = $reason;
            $locked->save();

            $this->audit->record('document.'.$target->value, $convert, newValues: array_filter(['document_id' => $locked->id, 'reason' => $reason]), actor: $actor);

            return $locked;
        });
    }

    /** @param  array{bytes: string, mime: string, extension: string}  $file */
    private function store(Document $document, Convert $convert, array $file, string $originalName, int $version): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantContext->requireId());
        $path = "tenants/{$tenant->uuid}/converts/{$convert->uuid}/documents/{$document->uuid}/v{$version}.{$file['extension']}";

        Storage::disk((string) config('documents.disk'))->put($path, $file['bytes']);

        $document->original_filename = Str::limit(basename($originalName), 250, '');
        $document->storage_path = $path;
        $document->mime_type = $file['mime'];
        $document->file_size = strlen($file['bytes']);
        $document->checksum = hash('sha256', $file['bytes']);
        $document->current_version = $version;
    }

    private function version(Document $document, User $actor): void
    {
        $version = new DocumentVersion;
        $version->document_id = $document->id;
        $version->version_no = $document->current_version;
        $version->storage_path = $document->storage_path;
        $version->checksum = $document->checksum;
        $version->uploaded_by = $actor->id;
        $version->save();
    }

    private function owner(Document $document): Convert
    {
        $convert = $document->owner_type === (new Convert)->getMorphClass() ? Convert::query()->find($document->owner_id) : null;

        return $convert ?? throw new AuthorizationException;
    }

    private function authorize(User $actor, string $ability, Convert $convert): void
    {
        if (! $actor->can($ability, $convert)) {
            throw new AuthorizationException;
        }
    }
}
