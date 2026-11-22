<?php

declare(strict_types=1);

namespace App\Domain\Dai\Models;

use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\FollowUpType;
use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Dai\DaiFollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only by RecordFollowUp (07-data-model-lifecycle §7.7).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $dai_id
 * @property string $subject_type
 * @property int $subject_id
 * @property FollowUpType $follow_up_type
 * @property ContactMethod $contact_method
 * @property CarbonImmutable $occurred_at
 * @property int|null $duration_minutes
 * @property string|null $location
 * @property string $summary
 * @property string|null $questions_raised
 * @property string|null $outcome
 * @property string|null $next_action
 * @property CarbonImmutable|null $next_follow_up_at
 * @property Confidentiality $confidentiality_level
 * @property int $created_by
 * @property-read Dai $dai
 */
#[UseFactory(DaiFollowUpFactory::class)]
final class DaiFollowUp extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DaiFollowUpFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return BelongsTo<Dai, $this> */
    public function dai(): BelongsTo
    {
        return $this->belongsTo(Dai::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Follow-ups of one subject the user may read: confidential notes only for
     * holders of followups.view_confidential and for their author.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleFor(Builder $query, Model $subject, User $user): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->when(! $user->hasPermission('followups.view_confidential'), fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('confidentiality_level', Confidentiality::Normal)
                    ->orWhere('created_by', $user->id)));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'follow_up_type' => FollowUpType::class,
            'contact_method' => ContactMethod::class,
            'occurred_at' => 'immutable_datetime',
            'next_follow_up_at' => 'immutable_datetime',
            'confidentiality_level' => Confidentiality::class,
        ];
    }
}
