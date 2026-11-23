<?php

declare(strict_types=1);

namespace App\Domain\Audit\Contracts;

/**
 * Implemented by models with sensitive attributes that are not already hidden.
 * AuditRedactor also redacts every attribute in the model's $hidden.
 */
interface RedactsAuditValues
{
    /** @return list<string> */
    public function auditRedactedAttributes(): array;
}
