<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $document_id
 * @property int $version_no
 * @property string $storage_path
 * @property string $checksum
 * @property int $uploaded_by
 * @property CarbonImmutable $created_at
 */
final class DocumentVersion extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $fillable = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'version_no' => 'integer'];
    }
}
