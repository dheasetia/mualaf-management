<?php

declare(strict_types=1);

namespace App\Domain\Converts\Models;

use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Scopes\ScopedThroughConvert;
use App\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. Scoped through the convert, not by tenant_id: the current
 * office reads the history written by earlier offices too (03 §3.3).
 *
 * @property int $id
 * @property int $convert_id
 * @property int $tenant_id
 * @property ConvertStatus|null $from_status
 * @property ConvertStatus $to_status
 * @property string|null $reason
 * @property int $changed_by
 * @property CarbonImmutable $created_at
 */
final class ConvertStatusHistory extends Model
{
    use AppendOnly;
    use ScopedThroughConvert;

    public const null UPDATED_AT = null;

    protected $fillable = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => ConvertStatus::class,
            'to_status' => ConvertStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
