<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

/**
 * How much of a convert profile a user may see (04-identity-privacy §4.6).
 */
enum ProfileAccess
{
    /** Whole profile; sensitive fields still need converts.view_sensitive. */
    case Full;

    /** Display name and operational minimum only (restricted converts). */
    case Minimal;

    /** Alias in lists only; the record cannot be opened. */
    case Hidden;
}
