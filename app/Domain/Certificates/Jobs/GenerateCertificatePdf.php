<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Jobs;

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Domain\Certificates\Services\CertificateVerifier;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Pdf\PdfRenderer;
use App\Support\Pdf\QrCode;
use App\Support\Pdf\TemplateRenderer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an issued certificate from its frozen snapshot and recorded
 * template version, with the QR code of its verification link, and stores
 * the PDF on the private disk with a SHA-256 checksum. The plaintext token
 * exists only in this job's payload and in the QR printed on the PDF.
 */
final class GenerateCertificatePdf implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'pdf';

    public int $tries = 5;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $certificateId, public readonly int $tenantId, public readonly string $token) {}

    public function handle(TenantContext $tenantContext, TemplateRenderer $renderer, PdfRenderer $pdf, CertificateVerifier $verifier): void
    {
        $tenantContext->run($this->tenantId, function () use ($renderer, $pdf, $verifier): void {
            $certificate = Certificate::query()->findOrFail($this->certificateId);
            $template = CertificateTemplate::query()->findOrFail($certificate->certificate_template_id);
            $tenant = Tenant::query()->findOrFail($this->tenantId);
            $token = (string) base64_decode($this->token, true);

            $html = $renderer->render($template->html_template, $certificate->snapshot_data ?? [], QrCode::svg($verifier->url($certificate, $token)));
            $bytes = $pdf->render($html, $template->orientation === 'portrait' ? 'portrait' : 'landscape');

            Storage::disk((string) config('certificates.disk'))->put($certificate->pdfPath($tenant->uuid), $bytes);

            $certificate->file_checksum = hash('sha256', $bytes);
            $certificate->save();
        });
    }
}
