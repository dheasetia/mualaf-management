<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditLogger;
use App\Support\Database\AppendOnlyTables;
use App\Support\Database\AppendOnlyViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Critical test #13 (14-testing §14.3): append-only tables reject UPDATE and DELETE in the database.

beforeEach(function (): void {
    $this->entry = app(AuditLogger::class)->record('test.recorded', newValues: ['value' => 'original']);
});

it('rejects UPDATE and DELETE from any database user through triggers', function (string $operation): void {
    $row = DB::table('audit_logs')->where('id', $this->entry->id);
    $write = $operation === 'update' ? fn () => $row->update(['action' => 'tampered']) : fn () => $row->delete();

    expect($write)->toThrow(function (QueryException $exception): void {
        expect($exception->getCode())->toBe('45000');
    });

    expect(DB::table('audit_logs')->where('id', $this->entry->id)->value('action'))->toBe('test.recorded');
})->with(['update', 'delete']);

it('refuses update and delete on the model', function (string $operation): void {
    $write = $operation === 'update'
        ? fn () => $this->entry->forceFill(['action' => 'tampered'])->save()
        : fn () => $this->entry->delete();

    expect($write)->toThrow(AppendOnlyViolation::class);
})->with(['update', 'delete']);

it('has both triggers on every registered append-only table', function (): void {
    $triggers = DB::table('information_schema.TRIGGERS')
        ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
        ->get(['EVENT_OBJECT_TABLE', 'EVENT_MANIPULATION'])
        ->map(fn (object $row): string => $row->EVENT_OBJECT_TABLE.':'.$row->EVENT_MANIPULATION)
        ->all();

    foreach (AppendOnlyTables::TABLES as $table) {
        expect($triggers)->toContain("{$table}:UPDATE", "{$table}:DELETE");
    }
});

it('does not grant UPDATE or DELETE on append-only tables to the application user', function (string $table): void {
    config(['database.connections.app_probe' => [
        ...config('database.connections.mysql'),
        'username' => env('DB_APP_USERNAME'),
        'password' => env('DB_APP_PASSWORD', ''),
    ]]);
    $app = DB::connection('app_probe');

    expect($app->table($table)->count())->toBeInt();

    foreach (['update' => fn () => $app->table($table)->where('id', 0)->update(['id' => 0]),
        'delete' => fn () => $app->table($table)->where('id', 0)->delete()] as $write) {
        // 1142: command denied to user, raised before any trigger or row lookup.
        expect($write)->toThrow(fn (QueryException $exception) => expect($exception->errorInfo[1] ?? null)->toBe(1142));
    }
})->with(AppendOnlyTables::TABLES)
    ->skip(fn (): bool => blank(env('DB_APP_USERNAME')), 'Set DB_APP_USERNAME/DB_APP_PASSWORD and run db:sync-app-grants on the test database.');

it('keeps the model class registered with its table', function (): void {
    expect(AppendOnlyTables::contains((new AuditLog)->getTable()))->toBeTrue();
});
