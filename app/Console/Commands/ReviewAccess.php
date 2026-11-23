<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Services\AccessReview;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Monthly access review summary (13 §13.6): per office and for platform
 * roles, how many accounts hold privileged roles without an authenticator
 * or have not been used. Recorded in the audit log for the auditors.
 */
final class ReviewAccess extends Command
{
    protected $signature = 'access:review';

    protected $description = 'Summarise accounts that need attention in each office and on the platform';

    public function handle(AccessReview $review, AuditLogger $audit): int
    {
        $lines = [];
        $scopes = [[null, 'platform'], ...Tenant::query()->orderBy('code')->get(['id', 'code'])->map(fn (Tenant $tenant): array => [$tenant->id, $tenant->code])->all()];

        foreach ($scopes as [$tenantId, $label]) {
            $rows = $review->rows($tenantId);
            $summary = [
                'accounts' => count($rows),
                'missing_mfa' => count(array_filter($rows, fn (array $row): bool => $row['missing_mfa'])),
                'stale' => count(array_filter($rows, fn (array $row): bool => $row['stale'])),
            ];

            $audit->record('access.reviewed', newValues: $summary, tenantId: $tenantId);
            $lines[] = [(string) $label, $summary['accounts'], $summary['missing_mfa'], $summary['stale']];
        }

        $this->table(['scope', 'accounts', 'privileged without MFA', 'unused'], $lines);

        return self::SUCCESS;
    }
}
