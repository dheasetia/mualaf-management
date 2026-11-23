<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Models;

use App\Domain\Prospects\Enums\ProspectStatus;
use App\Models\User;
use App\Support\Database\AppendOnly;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Prospects\ProspectStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only; written by ProspectStateMachine.
 *
 * @property int $id
 * @property int $prospect_id
 * @property int $tenant_id
 * @property ProspectStatus|null $from_status
 * @property ProspectStatus $to_status
 * @property string|null $reason
 * @property int $changed_by
 * @property CarbonImmutable $created_at
 */
#[UseFactory(ProspectStatusHistoryFactory::class)]
final class ProspectStatusHistory extends Model
{
    use AppendOnly;
    use BelongsToTenant;

    /** @use HasFactory<ProspectStatusHistoryFactory> */
    use HasFactory;

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
            'from_status' => ProspectStatus::class,
            'to_status' => ProspectStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
