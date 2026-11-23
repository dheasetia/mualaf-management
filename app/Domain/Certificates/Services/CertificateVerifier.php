<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Services;

use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;

/**
 * Public verification (09 §9.6): the QR carries /verify/{uuid}?t={token};
 * the token is compared with its stored SHA-256. Only number, type, issue
 * date, issuing office, status and the name (initials unless disclosure is
 * normal) are shown.
 */
final readonly class CertificateVerifier
{
    public function __construct(private TenantContext $tenantContext) {}

    public function url(Certificate $certificate, string $token): string
    {
        return route('certificates.verify', ['uuid' => $certificate->uuid, 't' => rtrim(strtr(base64_encode($token), '+/', '-_'), '=')]);
    }

    /**
     * @return array{number: string, type: string, issued_on: string, office: string, status: CertificateStatus, name: string}|null
     */
    public function verify(string $uuid, string $urlToken): ?array
    {
        $token = base64_decode(strtr($urlToken, '-_', '+/'), true);

        if ($token === false || strlen($token) !== 32) {
            return null;
        }

        $certificate = Certificate::query()->withoutGlobalScope(TenantScope::class)->where('uuid', $uuid)->first();

        if ($certificate === null || $certificate->verification_token_hash === null || $certificate->issued_at === null
            || ! hash_equals($certificate->verification_token_hash, hash('sha256', $token, binary: true))) {
            return null;
        }

        $convert = $this->tenantContext->bypass('certificate verification', fn (): ?Convert => Convert::query()->find($certificate->convert_id));
        $office = Tenant::query()->find($certificate->tenant_id);

        $name = $convert === null ? '—' : ($convert->disclosure_risk_level === DisclosureLevel::Normal ? $convert->full_name : $convert->initials());

        return [
            'number' => (string) $certificate->certificate_number,
            'type' => $certificate->certificate_type->label(),
            'issued_on' => $certificate->issued_at->setTimezone((string) config('app.display_timezone'))->toDateString(),
            'office' => (string) $office?->name_ar,
            'status' => $certificate->status,
            'name' => $name,
        ];
    }
}
