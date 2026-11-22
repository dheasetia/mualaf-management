<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\LetterType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Versioned letter template (BR-017): an approved version is never edited.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $template_code
 * @property LetterType $document_type
 * @property string $name_ar
 * @property string|null $description
 * @property string $html_template
 * @property string $orientation
 * @property int $version
 * @property bool $active
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $created_by
 */
final class DocumentTemplate extends Model
{
    protected $fillable = [];

    protected $guarded = ['national_version_key'];

    protected static function booted(): void
    {
        self::updating(function (self $template): void {
            if ($template->getOriginal('approved_at') !== null && $template->isDirty(['html_template', 'document_type', 'orientation'])) {
                throw new LogicException('An approved document template is changed by creating a new version.');
            }
        });
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => LetterType::class,
            'version' => 'integer',
            'active' => 'boolean',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
