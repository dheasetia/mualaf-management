<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\AppGrantPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run by a database administrator after every `migrate`: new tables are not
 * reachable by the application user until their grants exist.
 */
#[Signature('db:sync-app-grants
    {--user= : Application database user (default: DB_APP_USERNAME)}
    {--host= : Host part of the application account (default: DB_APP_HOST)}
    {--connection=dba : Connection of an account with GRANT OPTION}
    {--dry-run : Print the statements without running them}')]
#[Description('Give the application DB user table-level grants; append-only tables get SELECT and INSERT only')]
final class SyncAppGrants extends Command
{
    public function handle(): int
    {
        $connection = DB::connection((string) $this->option('connection'));
        $user = (string) ($this->option('user') ?: config('database.app_account.username'));
        $host = (string) ($this->option('host') ?: config('database.app_account.host'));

        $protected = [$connection->getConfig('username'), config('database.connections.migrator.username')];

        if (in_array($user, $protected, true)) {
            $this->error("[{$user}] is the administrator or migrator account; only the application account is managed.");

            return self::FAILURE;
        }

        $plan = new AppGrantPlan($connection->getDatabaseName(), $user, $host);

        $databasePrivileges = $connection->table('information_schema.SCHEMA_PRIVILEGES')
            ->where('GRANTEE', $plan->account)
            ->where('TABLE_SCHEMA', $plan->database)
            ->pluck('PRIVILEGE_TYPE')
            ->map(static fn (mixed $privilege): string => (string) $privilege)
            ->all();
        $databasePrivileges = array_values($databasePrivileges);

        if (! AppGrantPlan::isApplicationAccount($databasePrivileges)) {
            $this->error("{$plan->account} holds DDL privileges on {$plan->database}; refusing to change an owner account.");

            return self::FAILURE;
        }

        $tables = $connection->table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $plan->database)
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->pluck('TABLE_NAME')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        /** @var array<string, list<string>> $tablePrivileges */
        $tablePrivileges = [];
        $rows = $connection->table('information_schema.TABLE_PRIVILEGES')
            ->where('GRANTEE', $plan->account)
            ->where('TABLE_SCHEMA', $plan->database)
            ->get(['TABLE_NAME', 'PRIVILEGE_TYPE']);

        foreach ($rows as $row) {
            $tablePrivileges[(string) $row->TABLE_NAME][] = (string) $row->PRIVILEGE_TYPE;
        }

        $statements = $plan->statements(array_values($tables), $tablePrivileges, $databasePrivileges !== []);

        foreach ($statements as $statement) {
            $this->line($statement.';');

            if (! $this->option('dry-run')) {
                // GRANT and REVOKE are allowed as (non-emulated) prepared statements.
                $connection->statement($statement);
            }
        }

        $this->info(sprintf('%d statements %s for %s.', count($statements), $this->option('dry-run') ? 'planned' : 'applied', $plan->account));

        return self::SUCCESS;
    }
}
