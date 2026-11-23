<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ExportType;
use App\Domain\Reporting\Exceptions\ReportingRuleViolation;
use App\Domain\Reporting\Jobs\GenerateExport;
use App\Domain\Reporting\Models\ExportJob;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Exports (11 §11.5, 13 §13.4): reports.export, at most 5 an hour per user;
 * personal data needs a reason and only exists in an office context. The
 * file is an AES-256 zip whose password is shown once to the requester and
 * never stored; only the requester downloads it, through a short signed
 * link, until it expires after 24 hours.
 */
final readonly class Exports
{
    public const int PER_HOUR = 5;

    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: ExportJob, 1: string} the job and its one-time password
     */
    public function request(ExportType $type, array $filters, ?string $reason, User $actor): array
    {
        if (! $actor->hasPermission('reports.export')) {
            throw new AuthorizationException;
        }

        $tenantId = $this->tenantContext->id();

        if ($type->isSensitive() && $tenantId === null) {
            throw ReportingRuleViolation::centralOnly();
        }

        if ($type->isSensitive() && blank($reason)) {
            throw ReportingRuleViolation::reasonRequired();
        }

        $key = 'exports:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw ReportingRuleViolation::tooManyExports(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 3600);

        $export = new ExportJob;
        $export->tenant_id = $tenantId;
        $export->requested_by = $actor->id;
        $export->export_type = $type;
        $export->filters = $filters;
        $export->reason = blank($reason) ? null : trim((string) $reason);
        $export->status = ExportStatus::Queued;
        $export->save();

        $password = Str::password(20, symbols: false);

        $this->audit->record('export.requested', $export, newValues: ['type' => $type->value, 'reason' => $export->reason], actor: $actor);
        GenerateExport::dispatch($export->id, $tenantId, $password)->onQueue(GenerateExport::QUEUE);

        return [$export, $password];
    }

    public function downloadUrl(ExportJob $export, User $actor): string
    {
        if ($export->requested_by !== $actor->id || ! $actor->hasPermission('reports.export')) {
            throw new AuthorizationException;
        }

        if (! $export->isDownloadable() || $export->storage_path === null) {
            throw ReportingRuleViolation::notDownloadable();
        }

        $export->downloaded_at = CarbonImmutable::now();
        $export->download_count++;
        $export->save();

        $this->audit->record('export.downloaded', $export, newValues: ['count' => $export->download_count], actor: $actor);

        return Storage::disk((string) config('reports.disk'))->temporaryUrl($export->storage_path, CarbonImmutable::now()->addMinutes(5));
    }
}
