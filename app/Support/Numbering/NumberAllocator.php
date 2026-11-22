<?php

declare(strict_types=1);

namespace App\Support\Numbering;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gap-free numbers per office, type and period (11-data-model-platform §11.2).
 * Runs inside the caller's transaction: the sequence row stays locked until it
 * commits, and a rollback gives the number back.
 *
 * Lock order (05 §5.3): number_sequences is always locked last.
 */
final class NumberAllocator
{
    /**
     * @param  int|null  $tenantId  null for national sequences
     */
    public function next(?int $tenantId, string $type, string $period): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('NumberAllocator must run inside a transaction.');
        }

        $tenantKey = $tenantId ?? 0;

        // Creates the row when missing without locking a gap, then locks the existing row.
        DB::statement(
            'INSERT INTO number_sequences (tenant_id, sequence_type, period, next_value, created_at, updated_at) '
            .'VALUES (?, ?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE id = id',
            [$tenantKey, $type, $period],
        );

        $row = DB::table('number_sequences')
            ->where('tenant_id', $tenantKey)
            ->where('sequence_type', $type)
            ->where('period', $period)
            ->lockForUpdate()
            ->first(['id', 'next_value']);

        if ($row === null) {
            throw new LogicException('Number sequence row disappeared.');
        }

        $value = (int) $row->next_value;

        DB::table('number_sequences')->where('id', $row->id)->update([
            'next_value' => $value + 1,
            'updated_at' => now(),
        ]);

        return $value;
    }

    /** {CODE}-{KIND}-{YYYY}-{000001} */
    public static function format(string $prefix, string $kind, string $period, int $value): string
    {
        return sprintf('%s-%s-%s-%06d', $prefix, $kind, $period, $value);
    }
}
