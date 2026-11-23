<?php

declare(strict_types=1);

namespace App\Domain\Registration\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Registration\Enums\ReviewDecision;
use App\Domain\Registration\Enums\ReviewStatus;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * duplicates.review decision. same_person and different_person resolve the
 * case; inconclusive keeps it in review (the registration stays held) with
 * the reviewer's notes.
 */
final readonly class ResolveDuplicateCase
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(DuplicateReviewCase $case, ReviewDecision $decision, User $actor, ?string $notes): DuplicateReviewCase
    {
        $notes = blank($notes) ? null : trim((string) $notes);

        if ($notes === null) {
            throw RegistrationRuleViolation::reviewNotesRequired();
        }

        return DB::transaction(function () use ($case, $decision, $actor, $notes): DuplicateReviewCase {
            $locked = DuplicateReviewCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($locked->status === ReviewStatus::Resolved) {
                throw RegistrationRuleViolation::caseResolved();
            }

            $locked->decision = $decision;
            $locked->reviewed_by = $actor->id;
            $locked->notes = $notes;

            if ($decision === ReviewDecision::Inconclusive) {
                $locked->status = ReviewStatus::InReview;
            } else {
                $locked->status = ReviewStatus::Resolved;
                $locked->resolved_at = CarbonImmutable::now();
            }

            $locked->save();

            $this->audit->record('duplicates.resolved', newValues: [
                'case_id' => $locked->id,
                'decision' => $decision->value,
                'status' => $locked->status->value,
            ], actor: $actor);

            return $locked;
        });
    }
}
