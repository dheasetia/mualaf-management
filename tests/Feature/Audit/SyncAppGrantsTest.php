<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('plans table grants with SELECT and INSERT only on append-only tables', function (): void {
    $database = DB::getDatabaseName();

    $this->artisan('db:sync-app-grants', ['--connection' => 'mysql', '--user' => 'grant_probe', '--dry-run' => true])
        ->expectsOutputToContain("GRANT SELECT, INSERT ON `{$database}`.`audit_logs` TO 'grant_probe'@'localhost';")
        ->expectsOutputToContain("GRANT SELECT, INSERT, UPDATE, DELETE ON `{$database}`.`users` TO 'grant_probe'@'localhost';")
        ->doesntExpectOutputToContain('`migrations`')
        ->assertSuccessful();
});

it('refuses the administrator and migrator accounts', function (string $connection): void {
    $this->artisan('db:sync-app-grants', [
        '--connection' => 'mysql',
        '--user' => config("database.connections.{$connection}.username"),
        '--dry-run' => true,
    ])->assertFailed();
})->with(['mysql', 'migrator']);
