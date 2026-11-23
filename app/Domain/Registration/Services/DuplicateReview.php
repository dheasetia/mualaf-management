<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Converts\Models\Convert;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Support\Localization\LocalizedName;
use App\Support\Tenancy\TenantContext;

/**
 * What a reviewer may see of the possible convert, which can belong to
 * another office: the same limits as the national search (office and
 * initials), plus the fields the match was scored on.
 */
final readonly class DuplicateReview
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @return array{office: string, name: string, date_of_birth: ?string, nationality: ?string, same_office: bool}
     */
    public function possibleConvert(DuplicateReviewCase $case): array
    {
        $convert = $this->tenantContext->bypass('duplicate review', fn (): Convert => Convert::query()
            ->with(['currentTenant', 'nationality'])
            ->findOrFail($case->possible_convert_id));

        $sameOffice = $convert->current_tenant_id === $this->tenantContext->id();

        return [
            'office' => LocalizedName::of($convert->currentTenant)." ({$convert->currentTenant->code})",
            'name' => $sameOffice ? $convert->full_name : $convert->initials(),
            'date_of_birth' => $convert->date_of_birth?->toDateString(),
            'nationality' => LocalizedName::of($convert->nationality),
            'same_office' => $sameOffice,
        ];
    }
}
