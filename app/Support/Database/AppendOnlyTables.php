<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Registry of append-only tables (05-database-conventions §5.4) and the triggers
 * that reject UPDATE and DELETE on them. Add a table here in the same change as
 * its migration; db:sync-app-grants reads this list.
 */
final class AppendOnlyTables
{
    /** @var list<string> */
    public const array TABLES = [
        'audit_logs',
        'prospect_status_histories',
        'convert_status_histories',
        'convert_transfer_events',
        'convert_transfer_histories',
        'certificate_events',
        'document_versions',
        'submission_events',
        'pilgrimage_events',
        'convert_follow_up_statuses',
    ];

    public static function contains(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }

    /**
     * Creates the BEFORE UPDATE and BEFORE DELETE triggers. Requires SUPER or
     * log_bin_trust_function_creators=1 when binary logging is on.
     *
     * @param  literal-string  $table
     */
    public static function createTriggers(string $table): void
    {
        foreach (['update' => 'UPDATE', 'delete' => 'DELETE'] as $suffix => $event) {
            DB::unprepared(
                'CREATE TRIGGER `'.$table.'_no_'.$suffix.'` BEFORE '.$event.' ON `'.$table.'` FOR EACH ROW '
                ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '".$table." is append-only'"
            );
        }
    }

    /** @param  literal-string  $table */
    public static function dropTriggers(string $table): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `'.$table.'_no_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `'.$table.'_no_delete`');
    }
}
