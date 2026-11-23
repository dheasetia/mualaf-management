<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Contracts\RedactsAuditValues;
use Illuminate\Database\Eloquent\Model;

/**
 * Replaces sensitive values with [REDACTED] before they reach audit_logs
 * (04-identity-privacy §4.6). Keys are matched case-insensitively at any depth.
 */
final class AuditRedactor
{
    public const string MASK = '[REDACTED]';

    /** Redacted for every model and for free-form context. */
    private const array ALWAYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'secret',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'recovery_codes',
    ];

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redact(array $values, ?Model $model = null): array
    {
        $keys = self::ALWAYS;

        if ($model !== null) {
            $keys = [...$keys, ...$model->getHidden()];

            if ($model instanceof RedactsAuditValues) {
                $keys = [...$keys, ...$model->auditRedactedAttributes()];
            }
        }

        return $this->apply($values, array_flip(array_map(strtolower(...), $keys)));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<string, int>  $keys
     * @return array<array-key, mixed>
     */
    private function apply(array $values, array $keys): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && isset($keys[strtolower($key)])) {
                $values[$key] = self::MASK;
            } elseif (is_array($value)) {
                $values[$key] = $this->apply($value, $keys);
            }
        }

        return $values;
    }
}
