<?php

declare(strict_types=1);

namespace App\Domain\Dai\Services;

use App\Domain\Dai\Contracts\AssignableSubject;
use App\Domain\Dai\Models\DaiFollowUp;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Follow-ups follow their subject (03-tenancy-authorization §3.3): the office
 * now responsible for a person reads the follow-ups every earlier office
 * wrote. Any other office sees only the rows it wrote itself, so nothing
 * written after the person left it.
 *
 * Callers check that the user may see the subject; confidential notes are
 * filtered here (DaiFollowUp::visibleFor).
 */
final readonly class FollowUpHistory
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @param  Model&AssignableSubject  $subject
     * @return Collection<int, DaiFollowUp>
     */
    public function forSubject(Model $subject, User $user): Collection
    {
        $query = DaiFollowUp::query();

        if ($subject->responsibleTenantId() === $this->tenantContext->id()) {
            $query->withoutGlobalScope(TenantScope::class);
        }

        return $query->visibleFor($subject, $user)->with(['dai' => fn ($dai) => $dai->withoutGlobalScope(TenantScope::class)])
            ->latest('occurred_at')
            ->get();
    }
}
