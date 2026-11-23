<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Models\ExportJob;
use App\Domain\Reporting\Scopes\ExportOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * 13 §13.1: export files are deleted once they expire (24 hours).
 */
final class PurgeExpiredExports extends Command
{
    protected $signature = 'exports:purge';

    protected $description = 'Delete expired export files';

    public function handle(): int
    {
        $count = 0;

        ExportJob::query()->withoutGlobalScope(ExportOwnerScope::class)
            ->where('status', ExportStatus::Ready)->where('expires_at', '<', CarbonImmutable::now())
            ->each(function (ExportJob $export) use (&$count): void {
                if ($export->storage_path !== null) {
                    Storage::disk((string) config('reports.disk'))->delete($export->storage_path);
                }

                $export->status = ExportStatus::Expired;
                $export->storage_path = null;
                $export->save();
                $count++;
            });

        $this->info("{$count} export(s) expired.");

        return self::SUCCESS;
    }
}
