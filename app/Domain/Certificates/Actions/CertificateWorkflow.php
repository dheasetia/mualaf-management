<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Exceptions\CertificateRuleViolation;
use App\Domain\Certificates\Jobs\GenerateCertificatePdf;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Models\CertificateEvent;
use App\Domain\Certificates\Services\CertificateEligibility;
use App\Domain\Certificates\Services\SnapshotBuilder;
use App\Domain\Certificates\Services\TemplateResolver;
use App\Domain\Converts\Models\Convert;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Numbering\NumberAllocator;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Certificate lifecycle (12-transactions-state-machines §12.4):
 * request (certificates.request) → approve or reject (certificates.approve,
 * not by the requester) → issue (certificates.issue) → revoke
 * (certificates.revoke). Issuing freezes a snapshot, takes the next number
 * and a fresh verification token, and queues the PDF.
 */
final readonly class CertificateWorkflow
{
    public function __construct(
        private CertificateEligibility $eligibility,
        private SnapshotBuilder $snapshots,
        private TemplateResolver $templates,
        private NumberAllocator $numbers,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
    ) {}

    public function request(CertificateType $type, int $convertId, ?int $enrollmentId, User $actor): Certificate
    {
        $this->authorize($actor, 'certificates.request');

        if (! Convert::query()->whereKey($convertId)->exists()) {
            throw CertificateRuleViolation::convertUnavailable();
        }

        $this->eligibility->check($type, $convertId, $type === CertificateType::Shahadah ? null : $enrollmentId);

        try {
            return DB::transaction(function () use ($type, $convertId, $enrollmentId, $actor): Certificate {
                $certificate = new Certificate;
                $certificate->convert_id = $convertId;
                $certificate->enrollment_id = $type === CertificateType::Shahadah ? null : $enrollmentId;
                $certificate->certificate_type = $type;
                $certificate->status = CertificateStatus::PendingApproval;
                $certificate->requested_by = $actor->id;
                $certificate->save();

                $this->event($certificate, $actor, 'requested');
                $this->audit->record('certificate.requested', newValues: ['certificate_id' => $certificate->id, 'type' => $type->value], actor: $actor);

                return $certificate;
            });
        } catch (UniqueConstraintViolationException) {
            throw CertificateRuleViolation::alreadyExists();
        }
    }

    public function approve(Certificate $certificate, User $actor): Certificate
    {
        return $this->decide($certificate, $actor, CertificateStatus::Approved, null);
    }

    public function reject(Certificate $certificate, User $actor, ?string $reason): Certificate
    {
        if (blank($reason)) {
            throw CertificateRuleViolation::reasonRequired();
        }

        return $this->decide($certificate, $actor, CertificateStatus::Rejected, trim((string) $reason));
    }

    public function issue(Certificate $certificate, User $actor): Certificate
    {
        $this->authorize($actor, 'certificates.issue');
        $this->eligibility->check($certificate->certificate_type, $certificate->convert_id, $certificate->enrollment_id);

        $token = random_bytes(32);

        $issued = DB::transaction(function () use ($certificate, $actor, $token): Certificate {
            $locked = Certificate::query()->lockForUpdate()->findOrFail($certificate->id);

            if ($locked->status !== CertificateStatus::Approved) {
                throw CertificateRuleViolation::invalidTransition($locked->status->label(), CertificateStatus::Issued->label());
            }

            $this->eligibility->check($locked->certificate_type, $locked->convert_id, $locked->enrollment_id);

            $tenant = Tenant::query()->findOrFail($this->tenantContext->requireId());
            $convert = Convert::query()->findOrFail($locked->convert_id);
            $now = CarbonImmutable::now();
            $year = $now->setTimezone((string) config('app.display_timezone'))->format('Y');
            $type = $locked->certificate_type;

            $locked->certificate_template_id = $this->templates->for($type, $locked->enrollment_id)->id;
            $locked->certificate_number = NumberAllocator::format($tenant->code, $type->numberCode(), $year,
                $this->numbers->next($tenant->id, 'certificate_'.$type->value, $year));
            $locked->verification_token_hash = hash('sha256', $token, binary: true);
            $locked->issued_at = $now;
            $locked->issued_by = $actor->id;
            $locked->snapshot_data = $this->snapshots->build($locked, $convert, $tenant, $now);
            $locked->status = CertificateStatus::Issued;
            $locked->save();

            $this->event($locked, $actor, 'issued', ['certificate_number' => $locked->certificate_number]);
            $this->audit->record('certificate.issued', $convert, newValues: ['certificate_id' => $locked->id, 'certificate_number' => $locked->certificate_number], actor: $actor);

            return $locked;
        });

        GenerateCertificatePdf::dispatch($issued->id, $issued->tenant_id, base64_encode($token))->onQueue(GenerateCertificatePdf::QUEUE)->afterCommit();

        return $issued;
    }

    public function revoke(Certificate $certificate, User $actor, ?string $reason): Certificate
    {
        $this->authorize($actor, 'certificates.revoke');

        if (blank($reason)) {
            throw CertificateRuleViolation::reasonRequired();
        }

        return DB::transaction(function () use ($certificate, $actor, $reason): Certificate {
            $locked = Certificate::query()->lockForUpdate()->findOrFail($certificate->id);

            if ($locked->status !== CertificateStatus::Issued) {
                throw CertificateRuleViolation::invalidTransition($locked->status->label(), CertificateStatus::Revoked->label());
            }

            $locked->status = CertificateStatus::Revoked;
            $locked->revoked_at = CarbonImmutable::now();
            $locked->revoked_by = $actor->id;
            $locked->revocation_reason = trim((string) $reason);
            $locked->save();

            $this->event($locked, $actor, 'revoked', ['reason' => $locked->revocation_reason]);
            $this->audit->record('certificate.revoked', newValues: ['certificate_id' => $locked->id, 'reason' => $locked->revocation_reason], actor: $actor);

            return $locked;
        });
    }

    /**
     * New PDF from the same snapshot with a new token; the old QR stops
     * verifying (12 §12.4). Certificate Officer only.
     */
    public function regeneratePdf(Certificate $certificate, User $actor): void
    {
        $this->authorize($actor, 'certificates.issue');

        if ($certificate->status !== CertificateStatus::Issued) {
            throw CertificateRuleViolation::invalidTransition($certificate->status->label(), CertificateStatus::Issued->label());
        }

        $token = random_bytes(32);
        $certificate->verification_token_hash = hash('sha256', $token, binary: true);
        $certificate->file_checksum = null;
        $certificate->save();

        $this->event($certificate, $actor, 'pdf_regenerated');
        $this->audit->record('certificate.regenerated', newValues: ['certificate_id' => $certificate->id], actor: $actor);

        GenerateCertificatePdf::dispatch($certificate->id, $certificate->tenant_id, base64_encode($token))->onQueue(GenerateCertificatePdf::QUEUE);
    }

    private function decide(Certificate $certificate, User $actor, CertificateStatus $target, ?string $reason): Certificate
    {
        $this->authorize($actor, 'certificates.approve');

        return DB::transaction(function () use ($certificate, $actor, $target, $reason): Certificate {
            $locked = Certificate::query()->lockForUpdate()->findOrFail($certificate->id);

            if ($locked->status !== CertificateStatus::PendingApproval) {
                throw CertificateRuleViolation::invalidTransition($locked->status->label(), $target->label());
            }

            if ($locked->requested_by === $actor->id) {
                throw CertificateRuleViolation::selfApproval();
            }

            $locked->status = $target;
            $locked->approved_by = $actor->id;
            $locked->save();

            $this->event($locked, $actor, $target->value, array_filter(['reason' => $reason]));
            $this->audit->record('certificate.'.$target->value, newValues: array_filter(['certificate_id' => $locked->id, 'reason' => $reason]), actor: $actor);

            return $locked;
        });
    }

    /** @param  array<string, mixed>  $metadata */
    private function event(Certificate $certificate, ?User $actor, string $type, array $metadata = []): void
    {
        $event = new CertificateEvent;
        $event->certificate_id = $certificate->id;
        $event->actor_id = $actor?->id;
        $event->event_type = $type;
        $event->metadata = $metadata;
        $event->save();
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new AuthorizationException;
        }
    }
}
