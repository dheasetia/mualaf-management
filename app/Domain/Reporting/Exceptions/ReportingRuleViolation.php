<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class ReportingRuleViolation extends RuleViolation
{
    public static function reasonRequired(): self
    {
        return new self(__('reports.errors.reason_required'), 'reason');
    }

    public static function tooManyExports(int $seconds): self
    {
        return new self(__('reports.errors.too_many_exports', ['minutes' => (int) ceil($seconds / 60)]), 'export_type');
    }

    public static function centralOnly(): self
    {
        return new self(__('reports.errors.office_only'), 'export_type');
    }

    public static function notDownloadable(): self
    {
        return new self(__('reports.errors.not_downloadable'), 'status');
    }
}
