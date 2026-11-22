<?php

declare(strict_types=1);

namespace App\Domain\Documents\Jobs;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Pdf\PdfRenderer;
use App\Support\Pdf\TemplateRenderer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a letter from its frozen snapshot and recorded template version
 * into the private disk, with a SHA-256 checksum.
 */
final class GenerateLetterPdf implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'pdf';

    public int $tries = 5;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $generatedDocumentId, public readonly int $tenantId) {}

    public function handle(TenantContext $tenantContext, TemplateRenderer $renderer, PdfRenderer $pdf): void
    {
        $tenantContext->run($this->tenantId, function () use ($renderer, $pdf): void {
            $letter = GeneratedDocument::query()->findOrFail($this->generatedDocumentId);
            $template = DocumentTemplate::query()->findOrFail($letter->template_id);
            $tenant = Tenant::query()->findOrFail($this->tenantId);

            $bytes = $pdf->render($renderer->render($template->html_template, $letter->snapshot_data, null), $template->orientation === 'landscape' ? 'landscape' : 'portrait');

            Storage::disk((string) config('documents.disk'))->put($letter->pdfPath($tenant->uuid), $bytes);

            $letter->file_checksum = hash('sha256', $bytes);
            $letter->save();
        });
    }
}
