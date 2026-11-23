<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\VerificationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An uploaded file of a person (owner: convert or prospect). Access always
 * follows the owner's policy: no tenant scope here, because a convert's
 * files stay where they were uploaded and follow the convert after a
 * transfer (13 §13.1). Read only through DocumentLibrary.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property string $owner_type
 * @property int $owner_id
 * @property DocumentType $document_type
 * @property string $original_filename
 * @property string $storage_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $checksum
 * @property int $current_version
 * @property VerificationStatus $verification_status
 * @property int $uploaded_by
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $rejection_reason
 */
final class Document extends Model
{
    use HasUuids;

    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'file_size' => 'integer',
            'current_version' => 'integer',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
