<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Services;

use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Exceptions\CertificateRuleViolation;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Support\Tenancy\TenantContext;

/**
 * Template used at issue: the program's own template for completions when
 * set, else the office's active approved template of the type, else the
 * national one. The chosen version is recorded on the certificate.
 */
final readonly class TemplateResolver
{
    public function __construct(private EnrollmentDirectory $enrollments, private TenantContext $tenantContext) {}

    public function for(CertificateType $type, ?int $enrollmentId): CertificateTemplate
    {
        $programTemplateId = $enrollmentId === null ? null : ($this->enrollments->completion($enrollmentId)['certificate_template_id'] ?? null);

        $template = ($programTemplateId === null ? null : CertificateTemplate::query()->whereNotNull('approved_at')->find($programTemplateId))
            ?? CertificateTemplate::query()
                ->where('certificate_type', $type)
                ->where('active', true)
                ->whereNotNull('approved_at')
                ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $this->tenantContext->id()))
                ->orderByRaw('tenant_id IS NULL')
                ->orderByDesc('template_version')
                ->first();

        return $template ?? throw CertificateRuleViolation::noTemplate();
    }
}
