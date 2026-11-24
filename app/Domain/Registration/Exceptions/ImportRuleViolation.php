<?php

declare(strict_types=1);

namespace App\Domain\Registration\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class ImportRuleViolation extends RuleViolation
{
    public static function missingCell(string $column): self
    {
        return new self(__('imports.errors.missing_cell', ['column' => $column]), $column);
    }

    public static function invalidCell(string $column): self
    {
        return new self(__('imports.errors.invalid_cell', ['column' => $column]), $column);
    }

    public static function unreadableFile(): self
    {
        return new self(__('imports.errors.unreadable_file'), 'file');
    }

    public static function missingColumns(string $columns): self
    {
        return new self(__('imports.errors.missing_columns', ['columns' => $columns]), 'file');
    }

    public static function noRows(): self
    {
        return new self(__('imports.errors.no_rows'), 'file');
    }

    public static function tooManyRows(int $maximum): self
    {
        return new self(__('imports.errors.too_many_rows', ['count' => $maximum]), 'file');
    }

    public static function repeatedIdentity(int $rowNumber): self
    {
        return new self(__('imports.errors.repeated_identity', ['row' => $rowNumber]), 'identity_number');
    }

    public static function wrongStatus(): self
    {
        return new self(__('imports.errors.wrong_status'), 'batch');
    }

    public static function nothingToImport(): self
    {
        return new self(__('imports.errors.nothing_to_import'), 'batch');
    }
}
