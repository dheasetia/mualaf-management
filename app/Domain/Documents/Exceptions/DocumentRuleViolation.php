<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class DocumentRuleViolation extends RuleViolation
{
    public static function fileType(): self
    {
        return new self(__('documents.errors.file_type'), 'file');
    }

    public static function fileTooLarge(): self
    {
        return new self(__('documents.errors.file_too_large', ['mb' => (int) (config('documents.max_bytes') / 1048576)]), 'file');
    }

    public static function alreadyDecided(): self
    {
        return new self(__('documents.errors.already_decided'), 'verification_status');
    }

    public static function ownUpload(): self
    {
        return new self(__('documents.errors.own_upload'), 'verification_status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('documents.errors.reason_required'), 'reason');
    }

    public static function shahadahNotVerified(): self
    {
        return new self(__('documents.errors.shahadah_not_verified'), 'document_type');
    }

    public static function letterNotForConvert(): self
    {
        return new self(__('documents.errors.letter_not_for_convert'), 'document_type');
    }

    public static function khitanMaleOnly(): self
    {
        return new self(__('documents.errors.khitan_male_only'), 'document_type');
    }

    public static function noTemplate(): self
    {
        return new self(__('documents.errors.no_template'), 'document_type');
    }

    public static function notIssued(): self
    {
        return new self(__('documents.errors.not_issued'), 'status');
    }

    public static function selfApproval(): self
    {
        return new self(__('documents.errors.self_approval'), 'status');
    }

    public static function templateNotEditable(): self
    {
        return new self(__('documents.errors.template_not_editable'), 'html_template');
    }
}
