<?php

declare(strict_types=1);

namespace App\Support\Database;

use InvalidArgumentException;

/**
 * Grant statements that give the application database user table-level rights:
 * full DML on ordinary tables, only SELECT and INSERT on append-only tables
 * (05-database-conventions §5.4).
 *
 * MySQL cannot subtract a table from a database-level grant, so the
 * database-level grant is revoked last, after every table grant is in place.
 */
final readonly class AppGrantPlan
{
    private const array FULL = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];

    private const array APPEND_ONLY = ['SELECT', 'INSERT'];

    /** Database-level privileges an application account may hold; anything else means DDL rights. */
    private const array DML = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];

    /** Tables the application never touches at runtime. */
    private const array EXCLUDED = ['migrations'];

    public string $account;

    public function __construct(public string $database, string $user, string $host)
    {
        foreach ([$database, $user, $host] as $identifier) {
            if (preg_match('/^[A-Za-z0-9_.%-]+$/', $identifier) !== 1) {
                throw new InvalidArgumentException("Unsafe identifier [{$identifier}].");
            }
        }

        $this->account = "'{$user}'@'{$host}'";
    }

    /**
     * Whether an account with these database-level privileges may be managed:
     * an owner or migrator account (CREATE, DROP, ALTER, ...) never is, because
     * revoking its database grant would remove its DDL rights.
     *
     * @param  list<string>  $databasePrivileges
     */
    public static function isApplicationAccount(array $databasePrivileges): bool
    {
        return array_diff($databasePrivileges, self::DML) === [];
    }

    /**
     * @param  list<string>  $tables  base tables in the database
     * @param  array<string, list<string>>  $currentTablePrivileges  table => privileges the account holds now
     * @param  bool  $hasDatabaseGrant  whether the account holds privileges on `database`.*
     * @return list<string>
     */
    public function statements(array $tables, array $currentTablePrivileges, bool $hasDatabaseGrant): array
    {
        $statements = [];
        sort($tables);

        foreach (array_diff($tables, self::EXCLUDED) as $table) {
            $target = "`{$this->database}`.`{$table}`";

            if (! AppendOnlyTables::contains($table)) {
                $statements[] = sprintf('GRANT %s ON %s TO %s', implode(', ', self::FULL), $target, $this->account);

                continue;
            }

            $statements[] = sprintf('GRANT %s ON %s TO %s', implode(', ', self::APPEND_ONLY), $target, $this->account);

            $excess = array_values(array_intersect(['UPDATE', 'DELETE'], $currentTablePrivileges[$table] ?? []));

            if ($excess !== []) {
                $statements[] = sprintf('REVOKE %s ON %s FROM %s', implode(', ', $excess), $target, $this->account);
            }
        }

        if ($hasDatabaseGrant) {
            $statements[] = sprintf('REVOKE ALL PRIVILEGES ON `%s`.* FROM %s', $this->database, $this->account);
        }

        return $statements;
    }
}
