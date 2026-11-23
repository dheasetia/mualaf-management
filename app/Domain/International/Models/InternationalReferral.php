<?php

declare(strict_types=1);

namespace App\Domain\International\Models;

use App\Domain\International\Enums\ReferralStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\International\InternationalReferralFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Referral of a convert to follow-up abroad (10 §10.7). tenant_id is the
 * referring office (referring_tenant_id in the TDD).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $convert_id
 * @property int $origin_tenant_id
 * @property int $primary_dai_id
 * @property int $destination_country_id
 * @property string|null $destination_city
 * @property int|null $external_organization_id
 * @property string|null $external_contact_name
 * @property string|null $external_contact_phone
 * @property string $referral_reason
 * @property list<string> $information_scope
 * @property int|null $consent_record_id
 * @property ReferralStatus $status
 * @property CarbonImmutable|null $sent_at
 * @property int|null $sent_by
 * @property CarbonImmutable|null $acknowledged_at
 * @property CarbonImmutable|null $closed_at
 * @property string|null $close_reason
 * @property int $created_by
 */
#[UseFactory(InternationalReferralFactory::class)]
final class InternationalReferral extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<InternationalReferralFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    protected $table = 'international_follow_up_referrals';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'information_scope' => 'array',
            'status' => ReferralStatus::class,
            'sent_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
