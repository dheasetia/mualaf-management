<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * A business rule was broken. Carries the form field it concerns so a panel
 * can show it next to that field; the message is already translated.
 */
abstract class RuleViolation extends RuntimeException
{
    final protected function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }
}
