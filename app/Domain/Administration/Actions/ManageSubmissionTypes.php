<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Exceptions\SubmissionRuleViolation;
use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Models\SubmissionType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Documents\Actions\LetterWorkflow;
use App\Domain\Documents\Enums\LetterType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The national catalogue of submission types (10 §10.1), kept in Central by
 * master.manage. A type's code stays fixed once submissions use it; types
 * are deactivated, never deleted. Requirement codes are unique per type.
 */
final readonly class ManageSubmissionTypes
{
    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function save(?SubmissionType $type, array $data, User $actor): SubmissionType
    {
        if (! $actor->hasPermission('master.manage')) {
            throw new AuthorizationException;
        }

        $code = mb_strtolower(trim((string) $data['code']));

        if (preg_match('/^[a-z][a-z0-9_]{1,39}$/', $code) !== 1) {
            throw SubmissionRuleViolation::invalidTypeCode();
        }

        if ($type !== null && $type->code !== $code && $this->isInUse($type)) {
            throw SubmissionRuleViolation::typeCodeLocked();
        }

        $taken = SubmissionType::query()->where('code', $code);

        if ($type !== null) {
            $taken->whereKeyNot($type->id);
        }

        if ($taken->exists()) {
            throw SubmissionRuleViolation::typeCodeTaken();
        }

        $requirements = array_values(array_map(fn (array $row): array => [
            'code' => mb_strtolower(trim((string) $row['code'])),
            'name_ar' => trim((string) $row['name_ar']),
            'name_en' => trim((string) ($row['name_en'] ?? '')) ?: null,
            'required' => (bool) ($row['required'] ?? false),
        ], (array) ($data['required_documents'] ?? [])));
        $codes = array_column($requirements, 'code');

        if (count($codes) !== count(array_unique($codes)) || array_filter($codes, fn (string $value): bool => preg_match('/^[a-z][a-z0-9_]{1,39}$/', $value) !== 1) !== []) {
            throw SubmissionRuleViolation::invalidRequirements();
        }

        $letter = blank($data['generates_document_template_code'] ?? null) ? null : LetterType::from((string) $data['generates_document_template_code']);

        if ($letter !== null && ! in_array($letter, LetterWorkflow::CONVERT_LETTERS, true)) {
            throw SubmissionRuleViolation::invalidLetter();
        }

        $type ??= new SubmissionType;
        $type->code = $code;
        $type->name_ar = trim((string) $data['name_ar']);
        $type->name_en = blank($data['name_en'] ?? null) ? null : trim((string) $data['name_en']);
        $type->description = blank($data['description'] ?? null) ? null : trim((string) $data['description']);
        $type->required_documents = array_map(fn (array $row): array => array_filter($row, fn (mixed $value): bool => $value !== null), $requirements);
        $type->workflow_config = array_filter([
            'approval_stages' => min(5, max(1, (int) ($data['approval_stages'] ?? 1))),
            'sla_days' => blank($data['sla_days'] ?? null) ? null : min(365, max(1, (int) $data['sla_days'])),
        ], fn (mixed $value): bool => $value !== null);
        $type->generates_document_template_code = $letter?->value;
        $type->active = (bool) ($data['active'] ?? true);
        $type->save();

        $this->audit->recordChanges('submission_type.saved', $type, actor: $actor);

        return $type;
    }

    public function isInUse(SubmissionType $type): bool
    {
        return Submission::query()->withoutGlobalScopes()->where('submission_type_id', $type->id)->exists();
    }
}
