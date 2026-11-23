<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Models;

use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history of a program and its registrations.
 *
 * @property int $id
 * @property int $program_id
 * @property int|null $registration_id
 * @property int|null $actor_id
 * @property string $event_type
 * @property array<string, mixed> $metadata
 * @property CarbonImmutable $created_at
 */
final class PilgrimageEvent extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [];

    protected $table = 'pilgrimage_events';

    protected static function booted(): void
    {
        self::addGlobalScope('through_parent', fn (Builder $builder) => $builder->whereHas('program'));
    }

    /** @return BelongsTo<PilgrimageProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PilgrimageProgram::class, 'program_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
