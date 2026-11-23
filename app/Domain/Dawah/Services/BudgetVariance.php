<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Services;

/**
 * Budget variance (08 §8.3): actual minus approved (or estimated when not
 * yet approved), the same formula as the generated column, plus the share
 * of the baseline. Amounts are decimal strings in halalas-safe arithmetic.
 */
final class BudgetVariance
{
    public static function amount(string $estimated, ?string $approved, ?string $actual): string
    {
        return self::format(self::cents($actual ?? '0') - self::cents($approved ?? $estimated));
    }

    /** Variance as a fraction of the baseline; null when the baseline is zero. */
    public static function ratio(string $estimated, ?string $approved, string $actual): ?float
    {
        $baseline = self::cents($approved ?? $estimated);

        return $baseline === 0 ? null : (self::cents($actual) - $baseline) / $baseline;
    }

    /** Whether a recorded actual needs an explanation (threshold in config). */
    public static function needsExplanation(string $estimated, ?string $approved, string $actual): bool
    {
        $ratio = self::ratio($estimated, $approved, $actual);
        $threshold = (float) config('dawah.variance_note_threshold');

        return $ratio === null ? self::cents($actual) > 0 : abs($ratio) > $threshold;
    }

    public static function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
