<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Models\ExportJob;
use App\Domain\Reporting\Services\ExportBuilder;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds the CSV as the requester may see it and stores it in an AES-256
 * encrypted zip under exports/{uuid}.zip. The job payload (with the
 * password) is encrypted on the queue; without AES support the export
 * fails rather than being written in the clear.
 */
final class GenerateExport implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'exports';

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $exportId, public readonly ?int $tenantId, public readonly string $password) {}

    public function handle(TenantContext $tenantContext, ExportBuilder $builder): void
    {
        $run = function () use ($builder): void {
            $export = ExportJob::query()->findOrFail($this->exportId);
            $export->status = ExportStatus::Processing;
            $export->save();

            try {
                [$header, $rows] = $builder->rows($export->export_type, $export->tenant_id, $export->filters, User::query()->findOrFail($export->requested_by));
                $path = 'exports/'.$export->uuid.'.zip';
                Storage::disk((string) config('reports.disk'))->put($path, $this->zip($export->export_type->value.'.csv', $this->csv($header, $rows)));

                $export->status = ExportStatus::Ready;
                $export->storage_path = $path;
                $export->row_count = count($rows);
                $export->completed_at = CarbonImmutable::now();
                $export->expires_at = CarbonImmutable::now()->addHours((int) config('reports.export_hours'));
            } catch (Throwable $exception) {
                $export->status = ExportStatus::Failed;
                $export->error_summary = mb_substr($exception->getMessage(), 0, 500);
                report($exception);
            }

            $export->save();
        };

        $this->tenantId === null ? $run() : $tenantContext->run($this->tenantId, $run);
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string|int|null>>  $rows
     */
    private function csv(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'r+') ?: throw new RuntimeException('Cannot open a temporary stream.');
        fwrite($stream, "\xEF\xBB\xBF");

        foreach ([$header, ...$rows] as $row) {
            fputcsv($stream, array_map(fn (string|int|null $value): string => $this->safeCell((string) $value), $row), escape: '');
        }

        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /** Neutralises spreadsheet formulas (CSV injection). */
    private function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    private function zip(string $name, string $content): string
    {
        if (! ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)) {
            throw new RuntimeException('AES-256 zip encryption is not available.');
        }

        $file = tempnam(sys_get_temp_dir(), 'export') ?: throw new RuntimeException('Cannot create a temporary file.');
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->setPassword($this->password);
        $zip->addFromString($name, $content);
        $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $this->password);
        $zip->close();

        $bytes = (string) file_get_contents($file);
        unlink($file);

        return $bytes;
    }
}
