<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Models;

use App\Domain\Converts\Scopes\ScopedThroughConvert;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only; follows the convert (the current office reads all of it).
 *
 * @property int $id
 * @property int $convert_id
 * @property int $transfer_request_id
 * @property int $from_tenant_id
 * @property int $to_tenant_id
 * @property int $approved_by
 * @property int $accepted_by
 * @property int $previous_primary_dai_id
 * @property int $new_primary_dai_id
 * @property CarbonImmutable $transferred_at
 * @property string $reason
 */
final class ConvertTransferHistory extends Model
{
    use AppendOnly;
    use ScopedThroughConvert;

    public const null UPDATED_AT = null;

    protected $fillable = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transferred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
