<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Models;

use App\Domain\FollowUp\Enums\ActivityLevel;
use App\Domain\FollowUp\Enums\CommunityConnection;
use App\Domain\FollowUp\Enums\FollowUpState;
use App\Domain\FollowUp\Enums\Priority;
use App\Support\Database\AppendOnly;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\FollowUp\ConvertFollowUpStatusFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Long-term follow-up status (10 §10.3), append-only: the newest row is the
 * current one and converts.current_follow_up_status_id points at it. It is
 * a care note, never the convert's record status (PRD §31).
 *
 * @property int $id
 * @property int $convert_id
 * @property int $tenant_id
 * @property int $primary_dai_id
 * @property FollowUpState $status
 * @property CarbonImmutable|null $last_contact_at
 * @property string|null $last_contact_method
 * @property int|null $last_known_country_id
 * @property string|null $last_known_city
 * @property ActivityLevel|null $islamic_activity_status
 * @property CommunityConnection|null $community_connection_status
 * @property CarbonImmutable|null $next_follow_up_at
 * @property Priority $follow_up_priority
 * @property string|null $notes
 * @property int $recorded_by
 * @property CarbonImmutable $created_at
 */
#[UseFactory(ConvertFollowUpStatusFactory::class)]
final class ConvertFollowUpStatus extends Model
{
    use AppendOnly;
    use BelongsToTenant;

    /** @use HasFactory<ConvertFollowUpStatusFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    protected $fillable = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FollowUpState::class,
            'last_contact_at' => 'immutable_datetime',
            'islamic_activity_status' => ActivityLevel::class,
            'community_connection_status' => CommunityConnection::class,
            'next_follow_up_at' => 'immutable_datetime',
            'follow_up_priority' => Priority::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
