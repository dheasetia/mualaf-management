<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Registration\Services\ShahadahDirectory;
use App\Domain\Reporting\Enums\ExportType;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rows of an export, as the requester may see them:
 * - converts: the office's converts visible to the requester, names as the
 *   disclosure level allows, never high-confidentiality converts (04 §4.4),
 *   shahadah date only with the sensitive-data permission;
 * - shahadah statistics: monthly conversions per office from the aggregate
 *   tables (no personal data).
 */
final readonly class ExportBuilder
{
    public function __construct(private ConvertAccess $access, private ShahadahDirectory $shahadah) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|int|null>>}
     */
    public function rows(ExportType $type, ?int $tenantId, array $filters, User $requester): array
    {
        return match ($type) {
            ExportType::Converts => $this->converts($requester),
            ExportType::ShahadahStatistics => $this->shahadahStatistics($tenantId, $filters),
        };
    }

    /** @return array{0: list<string>, 1: list<list<string|int|null>>} */
    private function converts(User $requester): array
    {
        $header = ['reference', 'name', 'gender', 'nationality', 'status', 'shahadah_date', 'disclosure'];
        $rows = [];

        $this->access->visible(Convert::query(), $requester)
            ->where('disclosure_risk_level', '!=', DisclosureLevel::HighConfidentiality)
            ->with('nationality')->orderBy('id')
            ->each(function (Convert $convert) use (&$rows, $requester): void {
                $shahadah = $this->access->canSeeSensitive($requester, $convert) ? $this->shahadah->verifiedFirst($convert->id) : null;
                $rows[] = [
                    $convert->alias(),
                    $this->access->displayName($requester, $convert),
                    $convert->gender->label(),
                    (string) $convert->nationality?->getAttribute('name_ar'),
                    $convert->status->label(),
                    $shahadah === null ? null : $shahadah['date']->toDateString(),
                    $convert->disclosure_risk_level->value,
                ];
            });

        return [$header, $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|int|null>>}
     */
    private function shahadahStatistics(?int $tenantId, array $filters): array
    {
        $from = CarbonImmutable::parse((string) ($filters['from'] ?? CarbonImmutable::now()->startOfYear()->toDateString()));
        $to = CarbonImmutable::parse((string) ($filters['to'] ?? CarbonImmutable::now()->toDateString()));
        $offices = Tenant::query()->pluck('name_ar', 'id');

        $rows = DB::table('report_daily_tenant_stats')->where('metric', 'shahadah_verified')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->selectRaw("tenant_id, DATE_FORMAT(stat_date, '%Y-%m') as month, SUM(value) as total")
            ->groupBy('tenant_id', 'month')->orderBy('month')->orderBy('tenant_id')->get()
            ->map(fn (object $row): array => [(string) $row->month, (string) ($offices[$row->tenant_id] ?? $row->tenant_id), (int) $row->total])
            ->all();

        return [['month', 'office', 'shahadah_count'], array_values($rows)];
    }
}
