<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Enums\ApprovalDecision;
use App\Domain\Administration\Enums\AssignmentType;
use App\Domain\Administration\Enums\DocumentCheck;
use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Exceptions\SubmissionRuleViolation;
use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Models\SubmissionApproval;
use App\Domain\Administration\Models\SubmissionAssignment;
use App\Domain\Administration\Models\SubmissionDocument;
use App\Domain\Administration\Models\SubmissionEvent;
use App\Domain\Administration\Models\SubmissionType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Documents\Actions\Attachments;
use App\Domain\Documents\Actions\LetterWorkflow;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Exceptions\DocumentRuleViolation;
use App\Domain\Documents\Services\FileSafety;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Numbering\NumberAllocator;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Administrative submissions (10 §10.1, 12 §12.5):
 * - created and documented by submissions.create, submitted once every
 *   required document is attached; files belong to the submission, so
 *   medical letters are seen only with submissions.view;
 * - verified by submissions.verify document by document, or sent back for
 *   correction with a note;
 * - approved by submissions.approve over the type's stages, each by a
 *   different person and never the creator, once required documents are
 *   verified; approvals restart with each resubmission;
 * - processed by submissions.process; completing issues the type's letter;
 * - rejected or cancelled with a reason. Every step is an event.
 */
final readonly class SubmissionWorkflow
{
    public function __construct(
        private Attachments $attachments,
        private FileSafety $safety,
        private LetterWorkflow $letters,
        private ConvertAccess $converts,
        private NumberAllocator $numbers,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
    ) {}

    public function create(Convert $convert, SubmissionType $type, ?string $notes, User $actor): Submission
    {
        $this->require($actor->hasPermission('submissions.create'));

        if (! $this->converts->canSee($actor, $convert)) {
            throw SubmissionRuleViolation::convertUnavailable();
        }

        if (! $type->active) {
            throw SubmissionRuleViolation::typeUnavailable();
        }

        return DB::transaction(function () use ($convert, $type, $notes, $actor): Submission {
            $tenant = Tenant::query()->findOrFail($this->tenantContext->requireId());
            $year = CarbonImmutable::now()->setTimezone((string) config('app.display_timezone'))->format('Y');

            $submission = new Submission;
            $submission->convert_id = $convert->id;
            $submission->submission_type_id = $type->id;
            $submission->reference_no = NumberAllocator::format($tenant->code, 'SUB', $year, $this->numbers->next($tenant->id, 'submission', $year));
            $submission->status = SubmissionStatus::Draft;
            $submission->notes = blank($notes) ? null : trim((string) $notes);
            $submission->created_by = $actor->id;
            $submission->save();

            $this->event($submission, $actor, 'created', null, SubmissionStatus::Draft);
            $this->audit->record('submission.created', $convert, newValues: ['submission_id' => $submission->id, 'type' => $type->code], actor: $actor);

            return $submission;
        });
    }

    /** Attaches (or replaces) the file for one requirement while the submission is editable. */
    public function attach(Submission $submission, string $requirementCode, string $path, string $originalName, User $actor): SubmissionDocument
    {
        $this->require($actor->hasPermission('submissions.create'));
        $submission = $this->fresh($submission);

        if (! $submission->status->acceptsDocuments()) {
            throw SubmissionRuleViolation::documentsLocked();
        }

        if (! array_key_exists($requirementCode, $submission->type->requirementOptions())) {
            throw SubmissionRuleViolation::unknownRequirement();
        }

        $tenant = Tenant::query()->findOrFail($this->tenantContext->requireId());
        $documentId = $this->attachments->store(
            $submission,
            DocumentType::tryFrom($requirementCode) ?? DocumentType::Other,
            "tenants/{$tenant->uuid}/submissions/{$submission->uuid}",
            $path,
            $originalName,
            $actor,
        );

        return DB::transaction(function () use ($submission, $requirementCode, $documentId, $actor): SubmissionDocument {
            $submission->documents()->where('requirement_code', $requirementCode)->delete();

            $document = new SubmissionDocument;
            $document->submission_id = $submission->id;
            $document->document_id = $documentId;
            $document->requirement_code = $requirementCode;
            $document->verification_status = DocumentCheck::Pending;
            $document->save();

            $this->event($submission, $actor, 'document_attached', null, null, null, ['requirement' => $requirementCode]);

            return $document;
        });
    }

    public function submit(Submission $submission, User $actor): Submission
    {
        $this->require($actor->hasPermission('submissions.create'));
        $fresh = $this->fresh($submission);
        $attached = $fresh->documents()->pluck('requirement_code')->all();
        $missing = array_diff($fresh->type->requiredCodes(), $attached);

        if ($missing !== []) {
            $names = array_intersect_key($fresh->type->requirementOptions(), array_flip($missing));

            throw SubmissionRuleViolation::missingDocuments(implode('، ', $names));
        }

        return $this->move($submission, SubmissionStatus::Submitted, $actor, function (Submission $locked): void {
            $now = CarbonImmutable::now();
            $locked->submitted_at = $now;
            $locked->correction_note = null;
            $days = $locked->type->slaDays();
            $locked->due_at ??= $days === null ? null : $now->addDays($days);
        });
    }

    public function startVerification(Submission $submission, User $actor): Submission
    {
        $this->require($actor->hasPermission('submissions.verify'));

        return DB::transaction(function () use ($submission, $actor): Submission {
            $moved = $this->move($submission, SubmissionStatus::UnderVerification, $actor);
            $this->assignTo($moved, $actor, AssignmentType::Verifier);

            return $moved;
        });
    }

    public function checkDocument(SubmissionDocument $document, DocumentCheck $result, ?string $note, User $actor): SubmissionDocument
    {
        $this->require($actor->hasPermission('submissions.verify'));
        $submission = $this->fresh($document->submission()->firstOrFail());

        if ($submission->status !== SubmissionStatus::UnderVerification || $result === DocumentCheck::Pending) {
            throw SubmissionRuleViolation::invalidTransition($submission->status->label(), SubmissionStatus::UnderVerification->label());
        }

        if ($result === DocumentCheck::Verified && ! $this->safety->isSafe($document->document_id)) {
            throw DocumentRuleViolation::notScanned();
        }

        if ($result === DocumentCheck::Rejected && blank($note)) {
            throw SubmissionRuleViolation::reasonRequired();
        }

        $document->verification_status = $result;
        $document->verified_by = $actor->id;
        $document->verified_at = CarbonImmutable::now();
        $document->rejection_note = $result === DocumentCheck::Rejected ? trim((string) $note) : null;
        $document->save();

        $this->event($submission, $actor, 'document_'.$result->value, null, null, $document->rejection_note, ['requirement' => $document->requirement_code]);

        return $document;
    }

    public function requestCorrection(Submission $submission, User $actor, ?string $note): Submission
    {
        $this->require($actor->hasPermission('submissions.verify') || $actor->hasPermission('submissions.approve'));
        $note = $this->reason($note);

        return $this->move($submission, SubmissionStatus::NeedsCorrection, $actor, fn (Submission $locked) => $locked->correction_note = $note, $note);
    }

    public function approve(Submission $submission, User $actor, ?string $reason = null): Submission
    {
        $this->require($actor->hasPermission('submissions.approve'));

        return DB::transaction(function () use ($submission, $actor, $reason): Submission {
            $locked = Submission::query()->with('type')->lockForUpdate()->findOrFail($submission->id);

            if ($locked->status !== SubmissionStatus::UnderVerification) {
                throw SubmissionRuleViolation::invalidTransition($locked->status->label(), SubmissionStatus::Approved->label());
            }

            $verified = $locked->documents()->where('verification_status', DocumentCheck::Verified)->pluck('requirement_code')->all();

            if (array_diff($locked->type->requiredCodes(), $verified) !== [] || $locked->documents()->where('verification_status', '!=', DocumentCheck::Verified)->exists()) {
                throw SubmissionRuleViolation::documentsNotVerified();
            }

            $cycle = $this->currentApprovals($locked);

            if ($locked->created_by === $actor->id || in_array($actor->id, array_column($cycle, 'approver_id'), true)) {
                throw SubmissionRuleViolation::approverRepeated();
            }

            $stage = count($cycle) + 1;
            $this->recordApproval($locked, $stage, $actor, ApprovalDecision::Approved, $reason);

            if ($stage < $locked->type->approvalStages()) {
                $this->event($locked, $actor, 'stage_approved', null, null, $reason, ['stage' => $stage]);

                return $locked;
            }

            return $this->move($locked, SubmissionStatus::Approved, $actor, fn (Submission $final) => $final->approved_at = CarbonImmutable::now(), $reason);
        });
    }

    public function reject(Submission $submission, User $actor, ?string $reason): Submission
    {
        $this->require($actor->hasPermission('submissions.verify') || $actor->hasPermission('submissions.approve'));
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($submission, $actor, $reason): Submission {
            if ($actor->hasPermission('submissions.approve')) {
                $locked = $this->fresh($submission);

                if ($locked->status === SubmissionStatus::UnderVerification) {
                    $this->recordApproval($locked, count($this->currentApprovals($locked)) + 1, $actor, ApprovalDecision::Rejected, $reason);
                }
            }

            return $this->move($submission, SubmissionStatus::Rejected, $actor, null, $reason);
        });
    }

    public function startProcessing(Submission $submission, User $actor): Submission
    {
        $this->require($actor->hasPermission('submissions.process'));

        return DB::transaction(function () use ($submission, $actor): Submission {
            $moved = $this->move($submission, SubmissionStatus::Processing, $actor);
            $this->assignTo($moved, $actor, AssignmentType::Processor);

            return $moved;
        });
    }

    /** Completes the submission and issues its letter, addressed as given. */
    public function complete(Submission $submission, ?string $addressee, User $actor): Submission
    {
        $this->require($actor->hasPermission('submissions.process'));

        return DB::transaction(function () use ($submission, $addressee, $actor): Submission {
            $locked = Submission::query()->with('type')->lockForUpdate()->findOrFail($submission->id);
            $letterType = LetterType::tryFrom((string) $locked->type->generates_document_template_code);

            if ($letterType !== null && $locked->status === SubmissionStatus::Processing) {
                $letter = $this->letters->generate(Convert::query()->findOrFail($locked->convert_id), $letterType, ['addressee' => $addressee, 'body' => null], $actor);
                $locked->generated_document_id = $letter->id;
            }

            $completed = $this->move($locked, SubmissionStatus::Completed, $actor, function (Submission $final) use ($locked): void {
                $final->completed_at = CarbonImmutable::now();
                $final->generated_document_id = $locked->generated_document_id;
            });

            SubmissionAssignment::query()->where('submission_id', $completed->id)->whereNull('completed_at')->update(['completed_at' => CarbonImmutable::now()]);

            return $completed;
        });
    }

    public function cancel(Submission $submission, User $actor, ?string $reason): Submission
    {
        $isOwnEarly = $submission->created_by === $actor->id && $actor->hasPermission('submissions.create')
            && in_array($submission->status, [SubmissionStatus::Draft, SubmissionStatus::Submitted, SubmissionStatus::NeedsCorrection], true);
        $this->require($isOwnEarly || $actor->hasPermission('submissions.approve'));
        $reason = $this->reason($reason);

        return $this->move($submission, SubmissionStatus::Cancelled, $actor, null, $reason);
    }

    /** Assigns a verifier, processor or approver who holds the matching permission here. */
    public function assign(Submission $submission, User $assignee, AssignmentType $type, User $actor): SubmissionAssignment
    {
        $this->require($actor->hasPermission('submissions.verify') || $actor->hasPermission('submissions.approve'));

        if (! $assignee->hasPermission($type->permission())) {
            throw SubmissionRuleViolation::assigneeLacksPermission();
        }

        return DB::transaction(function () use ($submission, $assignee, $type, $actor): SubmissionAssignment {
            $assignment = $this->assignTo($submission, $assignee, $type);
            $this->event($submission, $actor, 'assigned', null, null, null, ['user_id' => $assignee->id, 'type' => $type->value]);

            return $assignment;
        });
    }

    /** Signed short-lived link to an attached file (submissions.view). */
    public function documentUrl(SubmissionDocument $document, User $actor): string
    {
        $this->require($actor->hasPermission('submissions.view'));

        return $this->attachments->downloadUrl($document->submission()->firstOrFail(), $document->document_id, $actor);
    }

    private function assignTo(Submission $submission, User $assignee, AssignmentType $type): SubmissionAssignment
    {
        SubmissionAssignment::query()->where('submission_id', $submission->id)->where('assignment_type', $type)->whereNull('completed_at')
            ->update(['completed_at' => CarbonImmutable::now()]);

        $assignment = new SubmissionAssignment;
        $assignment->submission_id = $submission->id;
        $assignment->user_id = $assignee->id;
        $assignment->assignment_type = $type;
        $assignment->assigned_at = CarbonImmutable::now();
        $assignment->save();

        Submission::query()->whereKey($submission->id)->update(['assigned_to' => $assignee->id]);

        return $assignment;
    }

    /**
     * Approvals of the current cycle (since the last submission).
     *
     * @return list<array{approver_id: int}>
     */
    private function currentApprovals(Submission $submission): array
    {
        return array_values(SubmissionApproval::query()
            ->where('submission_id', $submission->id)
            ->where('decision', ApprovalDecision::Approved)
            ->when($submission->submitted_at !== null, fn ($query) => $query->where('decided_at', '>=', $submission->submitted_at))
            ->get(['approver_id'])
            ->map(fn (SubmissionApproval $approval): array => ['approver_id' => $approval->approver_id])
            ->all());
    }

    private function recordApproval(Submission $submission, int $stage, User $actor, ApprovalDecision $decision, ?string $reason): void
    {
        $approval = new SubmissionApproval;
        $approval->submission_id = $submission->id;
        $approval->approval_stage = $stage;
        $approval->approver_id = $actor->id;
        $approval->decision = $decision;
        $approval->reason = $reason;
        $approval->decided_at = CarbonImmutable::now();
        $approval->save();
    }

    /** @param  (Closure(Submission): mixed)|null  $apply */
    private function move(Submission $submission, SubmissionStatus $target, User $actor, ?Closure $apply = null, ?string $reason = null): Submission
    {
        return DB::transaction(function () use ($submission, $target, $actor, $apply, $reason): Submission {
            $locked = Submission::query()->with('type')->lockForUpdate()->findOrFail($submission->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw SubmissionRuleViolation::invalidTransition($from->label(), $target->label());
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->status = $target;
            $locked->save();

            $this->event($locked, $actor, 'status_changed', $from, $target, $reason);
            $this->audit->record('submission.status_changed', $locked, ['status' => $from->value], array_filter(['status' => $target->value, 'reason' => $reason]), actor: $actor);

            return $locked;
        });
    }

    /** @param  array<string, mixed>  $metadata */
    private function event(Submission $submission, ?User $actor, string $type, ?SubmissionStatus $from, ?SubmissionStatus $to, ?string $reason = null, array $metadata = []): void
    {
        $event = new SubmissionEvent;
        $event->submission_id = $submission->id;
        $event->actor_id = $actor?->id;
        $event->event_type = $type;
        $event->from_status = $from?->value;
        $event->to_status = $to?->value;
        $event->reason = $reason;
        $event->metadata = $metadata;
        $event->save();
    }

    private function fresh(Submission $submission): Submission
    {
        return Submission::query()->with('type')->findOrFail($submission->id);
    }

    private function reason(?string $reason): string
    {
        return blank($reason) ? throw SubmissionRuleViolation::reasonRequired() : trim((string) $reason);
    }

    private function require(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
