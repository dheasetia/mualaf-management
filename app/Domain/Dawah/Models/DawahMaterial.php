<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Models;

use App\Domain\Dawah\Enums\MaterialStatus;
use App\Domain\Dawah\Enums\MaterialType;
use App\Domain\Dawah\Scopes\MaterialScope;
use App\Domain\Platform\Models\Language;
use Carbon\CarbonImmutable;
use Database\Factories\Dawah\DawahMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dawah material (08 §8.4), national or an office's own. A new version is a
 * new record pointing at its parent; approving it retires the parent.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property string $title
 * @property string|null $title_ar
 * @property MaterialType $material_type
 * @property int $language_id
 * @property string|null $topic
 * @property string|null $target_audience
 * @property string|null $description
 * @property int|null $file_document_id
 * @property string|null $external_url
 * @property string $version
 * @property int|null $parent_material_id
 * @property MaterialStatus $status
 * @property string|null $review_note
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $stock_quantity
 * @property int $created_by
 * @property-read Language $language
 */
#[UseFactory(DawahMaterialFactory::class)]
final class DawahMaterial extends Model
{
    /** @use HasFactory<DawahMaterialFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    protected static function booted(): void
    {
        self::addGlobalScope(new MaterialScope);
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function isNational(): bool
    {
        return $this->tenant_id === null;
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'material_type' => MaterialType::class,
            'status' => MaterialStatus::class,
            'approved_at' => 'immutable_datetime',
            'stock_quantity' => 'integer',
        ];
    }
}
