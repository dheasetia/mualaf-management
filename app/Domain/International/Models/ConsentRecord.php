<?php

declare(strict_types=1);

namespace App\Domain\International\Models;

use App\Domain\International\Enums\ConsentMethod;
use App\Domain\International\Enums\ConsentType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\International\ConsentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A convert's consent (10 §10.8). Active = given, not withdrawn, not expired.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $convert_id
 * @property ConsentType $consent_type
 * @property array{fields?: list<string>, destination_country_id?: int|null} $scope
 * @property CarbonImmutable $given_at
 * @property ConsentMethod $method
 * @property int|null $evidence_document_id
 * @property int|null $witnessed_by
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property string|null $withdrawn_reason
 * @property int $recorded_by
 */
#[UseFactory(ConsentRecordFactory::class)]
final class ConsentRecord extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ConsentRecordFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function isActive(): bool
    {
        return $this->withdrawn_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** @return list<string> */
    public function fields(): array
    {
        return $this->scope['fields'] ?? [];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'consent_type' => ConsentType::class,
            'scope' => 'array',
            'given_at' => 'immutable_datetime',
            'method' => ConsentMethod::class,
            'expires_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
