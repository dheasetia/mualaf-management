<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Models;

use App\Domain\Certificates\Enums\CertificateType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Versioned HTML template. An approved version is never edited: a change
 * is a new version that needs its own approval. National templates have
 * tenant_id NULL.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $template_code
 * @property CertificateType $certificate_type
 * @property string $name
 * @property int $template_version
 * @property string $html_template
 * @property array<string, mixed> $configuration
 * @property string $paper_size
 * @property string $orientation
 * @property bool $active
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $created_by
 */
final class CertificateTemplate extends Model
{
    protected $fillable = [];

    protected $guarded = ['national_version_key'];

    protected static function booted(): void
    {
        self::updating(function (self $template): void {
            if ($template->getOriginal('approved_at') !== null && $template->isDirty(['html_template', 'configuration', 'paper_size', 'orientation', 'certificate_type'])) {
                throw new LogicException('An approved certificate template is changed by creating a new version.');
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
            'certificate_type' => CertificateType::class,
            'template_version' => 'integer',
            'configuration' => 'array',
            'active' => 'boolean',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
