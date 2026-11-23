<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Audit\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * APP_KEY rotation (runbook §7): with the old key in APP_PREVIOUS_KEYS and
 * the new one in APP_KEY, rewrites every column cast as `encrypted*` in
 * every model with the current key. Laravel decrypts with the previous
 * keys, so the command is safe to re-run; only when it reports no failures
 * may the old key be removed. Columns are found from the models' casts, so
 * new encrypted columns are covered without changing this command.
 */
final class ReencryptData extends Command
{
    protected $signature = 'security:reencrypt {--dry-run : Count only, write nothing}';

    protected $description = 'Re-encrypt all encrypted columns with the current APP_KEY';

    public function handle(AuditLogger $audit): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = [];
        $failures = 0;

        foreach ($this->encryptedColumns() as $table => $columns) {
            $rewritten = 0;

            DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $dryRun, &$rewritten, &$failures): void {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column};

                        if ($value === null || $value === '') {
                            continue;
                        }

                        try {
                            $changes[$column] = Crypt::encryptString(Crypt::decryptString((string) $value));
                        } catch (DecryptException) {
                            $failures++;
                            $this->error("{$table}#{$row->id}.{$column} cannot be decrypted with APP_KEY or APP_PREVIOUS_KEYS");
                        }
                    }

                    if ($changes !== [] && ! $dryRun) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }

                    $rewritten += count($changes);
                }
            });

            $summary[$table] = $rewritten;
            $this->line(sprintf('%-40s %s value(s)%s', $table, $rewritten, $dryRun ? ' (dry run)' : ''));
        }

        if (! $dryRun) {
            $audit->record('security.reencrypted', newValues: ['tables' => $summary, 'failures' => $failures]);
        }

        if ($failures > 0) {
            $this->error("{$failures} value(s) could not be decrypted; keep the old key until they are resolved.");

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry run complete.' : 'All encrypted values now use the current APP_KEY.');

        return self::SUCCESS;
    }

    /**
     * Table => columns cast as encrypted, from every model under app/.
     *
     * @return array<string, list<string>>
     */
    private function encryptedColumns(): array
    {
        $found = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            /** @var Model $model */
            $model = new $class;
            $columns = array_keys(array_filter($model->getCasts(), fn (string $cast): bool => str_starts_with($cast, 'encrypted')));

            if ($columns !== []) {
                $found[$model->getTable()] = array_values(array_unique([...($found[$model->getTable()] ?? []), ...$columns]));
            }
        }

        ksort($found);

        return $found;
    }
}
