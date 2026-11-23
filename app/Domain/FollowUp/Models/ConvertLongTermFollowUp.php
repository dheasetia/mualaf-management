<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Models;

use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\FollowUp\Enums\ContactedPerson;
use App\Domain\FollowUp\Enums\ContactMethod;
use App\Domain\FollowUp\Enums\ContactResult;
use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\FollowUp\ConvertLongTermFollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A long-term follow-up contact (10 §10.5).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $convert_id
 * @property int $dai_id
 * @property CarbonImmutable $follow_up_at
 * @property ContactMethod $contact_method
 * @property ContactedPerson $contacted_person_type
 * @property ContactResult $contact_result
 * @property int|null $current_country_id
 * @property string|null $current_city
 * @property string|null $mosque_or_center_name
 * @property string|null $local_dai_name
 * @property string|null $support_needed
 * @property string|null $next_action
 * @property CarbonImmutable|null $next_follow_up_at
 * @property Confidentiality $confidentiality_level
 * @property string|null $notes
 * @property int $recorded_by
 */
#[UseFactory(ConvertLongTermFollowUpFactory::class)]
final class ConvertLongTermFollowUp extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ConvertLongTermFollowUpFactory> */
    use HasFactory;

    protected $fillable = [];

    /**
     * Notes the user may read: confidential ones only with
     * followups.view_confidential or for their author.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(! $user->hasPermission('followups.view_confidential'), fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('confidentiality_level', Confidentiality::Normal)->orWhere('recorded_by', $user->id)));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'follow_up_at' => 'immutable_datetime',
            'contact_method' => ContactMethod::class,
            'contacted_person_type' => ContactedPerson::class,
            'contact_result' => ContactResult::class,
            'next_follow_up_at' => 'immutable_datetime',
            'confidentiality_level' => Confidentiality::class,
        ];
    }
}
