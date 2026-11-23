<?php

declare(strict_types=1);

namespace App\Domain\Administration\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class SubmissionRuleViolation extends RuleViolation
{
    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('submissions.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('submissions.errors.reason_required'), 'reason');
    }

    public static function convertUnavailable(): self
    {
        return new self(__('submissions.errors.convert_unavailable'), 'convert_id');
    }

    public static function typeUnavailable(): self
    {
        return new self(__('submissions.errors.type_unavailable'), 'submission_type_id');
    }

    public static function unknownRequirement(): self
    {
        return new self(__('submissions.errors.unknown_requirement'), 'requirement_code');
    }

    public static function documentsLocked(): self
    {
        return new self(__('submissions.errors.documents_locked'), 'file');
    }

    public static function missingDocuments(string $names): self
    {
        return new self(__('submissions.errors.missing_documents', ['documents' => $names]), 'status');
    }

    public static function documentsNotVerified(): self
    {
        return new self(__('submissions.errors.documents_not_verified'), 'status');
    }

    public static function approverRepeated(): self
    {
        return new self(__('submissions.errors.approver_repeated'), 'status');
    }

    public static function assigneeLacksPermission(): self
    {
        return new self(__('submissions.errors.assignee_lacks_permission'), 'user_id');
    }
}
