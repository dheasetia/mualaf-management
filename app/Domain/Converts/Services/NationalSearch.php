<?php

declare(strict_types=1);

namespace App\Domain\Converts\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Data\NationalSearchResult;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Exceptions\IdentityRuleViolation;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Exact identity search across offices (03-tenancy-authorization §3.4): the
 * only cross-office read in normal operation. Limited to 30 searches per
 * hour per user; every search is audited, found or not, without the number.
 */
final readonly class NationalSearch
{
    public const int MAX_PER_HOUR = 30;

    public function __construct(
        private IdentityRegistry $registry,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
    ) {}

    public function search(User $actor, IdentifierData $data): NationalSearchResult
    {
        if (! $actor->hasPermission('identity.national_search')) {
            throw new AuthorizationException;
        }

        $key = 'national-search:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            throw IdentityRuleViolation::searchLimitReached((int) ceil(RateLimiter::availableIn($key) / 60));
        }

        // Invalid numbers are rejected before they count or reach the audit.
        $this->registry->fingerprint($data);
        RateLimiter::hit($key, 3600);

        $identifier = $this->registry->findActive($data);

        $this->audit->recordLater('identity.national_search', newValues: [
            'identity_type' => $data->type->value,
            'issuing_country_id' => $data->issuingCountryId,
            'found' => $identifier !== null,
        ], actor: $actor);

        if ($identifier === null) {
            return NationalSearchResult::notFound();
        }

        $convert = $identifier->convert;
        $inOwnOffice = $convert->current_tenant_id === $this->tenantContext->id();

        if ($convert->disclosure_risk_level === DisclosureLevel::HighConfidentiality) {
            return new NationalSearchResult(found: true, inOwnOffice: $inOwnOffice, withheld: true);
        }

        $fullName = $convert->disclosure_risk_level === DisclosureLevel::Normal && $actor->hasPermission('converts.transfer');

        return new NationalSearchResult(
            found: true,
            inOwnOffice: $inOwnOffice,
            officeCode: $convert->currentTenant->code,
            officeName: (string) LocalizedName::of($convert->currentTenant),
            displayName: $fullName ? $convert->full_name : $convert->initials(),
        );
    }
}
