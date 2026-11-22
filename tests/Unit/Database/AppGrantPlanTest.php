<?php

declare(strict_types=1);

use App\Support\Database\AppGrantPlan;

beforeEach(function (): void {
    $this->plan = new AppGrantPlan('muallaf', 'muallaf_app', 'localhost');
});

it('revokes only the UPDATE and DELETE an append-only table still holds', function (): void {
    expect($this->plan->statements(['audit_logs'], ['audit_logs' => ['SELECT', 'INSERT', 'DELETE']], false))->toBe([
        "GRANT SELECT, INSERT ON `muallaf`.`audit_logs` TO 'muallaf_app'@'localhost'",
        "REVOKE DELETE ON `muallaf`.`audit_logs` FROM 'muallaf_app'@'localhost'",
    ]);
});

it('revokes the database-level grant after every table grant', function (): void {
    $statements = $this->plan->statements(['users', 'audit_logs'], [], true);

    expect($statements)->toHaveCount(3)
        ->and(end($statements))->toBe("REVOKE ALL PRIVILEGES ON `muallaf`.* FROM 'muallaf_app'@'localhost'");
});

it('treats only DML-only accounts as application accounts', function (array $privileges, bool $expected): void {
    expect(AppGrantPlan::isApplicationAccount($privileges))->toBe($expected);
})->with([
    'no database grant' => [[], true],
    'DML only' => [['SELECT', 'INSERT', 'UPDATE', 'DELETE'], true],
    'owner with DDL' => [['SELECT', 'INSERT', 'CREATE', 'DROP', 'ALTER'], false],
]);

it('rejects identifiers that could inject SQL', function (): void {
    new AppGrantPlan('muallaf', "app'@'%' WITH GRANT OPTION --", 'localhost');
})->throws(InvalidArgumentException::class);
