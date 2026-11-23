<?php

declare(strict_types=1);

namespace App\Domain\Converts\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Exceptions\ConvertAlreadyRegistered;
use App\Domain\Converts\Exceptions\IdentityRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Platform\Models\Country;
use App\Models\User;
use App\Support\Identity\Fingerprint;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of convert_identifiers (04-identity-privacy §4.2–4.4).
 */
final readonly class IdentityRegistry
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    /**
     * Normalised number, its fingerprint and the issuing country it was computed for.
     *
     * @return array{normalized: string, fingerprint: string, country: Country}
     */
    public function fingerprint(IdentifierData $data): array
    {
        $country = $this->issuingCountry($data->type, $data->issuingCountryId);
        $normalized = IdentityNumber::normalize($data->type, $data->number);

        return [
            'normalized' => $normalized,
            'fingerprint' => Fingerprint::make($data->type->value.'|'.$country->iso2, $normalized),
            'country' => $country,
        ];
    }

    /**
     * The active identifier with this identity, in any office. Only for
     * services that limit what they disclose (NationalSearch, registration).
     */
    public function findActive(IdentifierData $data): ?ConvertIdentifier
    {
        ['fingerprint' => $fingerprint, 'country' => $country] = $this->fingerprint($data);

        return $this->tenantContext->bypass('identity lookup', fn (): ?ConvertIdentifier => ConvertIdentifier::query()
            ->with(['convert.currentTenant'])
            ->where('identity_type', $data->type)
            ->where('issuing_country_id', $country->id)
            ->where('active_fingerprint', $fingerprint)
            ->first());
    }

    /**
     * Adds an identifier to a convert of the office in context. Runs inside the
     * caller's transaction (the shahadah registration in step 11): when the
     * identity is already active anywhere, the database unique key rejects the
     * insert and ConvertAlreadyRegistered is thrown, so the caller rolls back.
     */
    public function add(Convert $convert, IdentifierData $data, User $actor, bool $primary = false): ConvertIdentifier
    {
        ['normalized' => $normalized, 'fingerprint' => $fingerprint, 'country' => $country] = $this->fingerprint($data);

        return DB::transaction(function () use ($convert, $data, $actor, $primary, $normalized, $fingerprint, $country): ConvertIdentifier {
            if ($primary) {
                ConvertIdentifier::query()->where('convert_id', $convert->id)->where('is_primary', true)->update(['is_primary' => false]);
            }

            $identifier = new ConvertIdentifier;
            $identifier->convert_id = $convert->id;
            $identifier->identity_type = $data->type;
            $identifier->identity_number_encrypted = $normalized;
            $identifier->identity_last4 = substr($normalized, -4);
            $identifier->identity_fingerprint = $fingerprint;
            $identifier->fingerprint_key_version = (int) config('identity.hmac_key_version');
            $identifier->issuing_country_id = $country->id;
            $identifier->expires_on = $data->expiresOn;
            $identifier->is_primary = $primary;
            $identifier->created_by = $actor->id;

            try {
                $identifier->save();
            } catch (UniqueConstraintViolationException $exception) {
                if (str_contains($exception->getMessage(), 'uq_convert_identifiers_active_fingerprint')) {
                    throw ConvertAlreadyRegistered::make();
                }

                throw $exception;
            }

            $this->audit->record('identity.added', $convert, newValues: [
                'identifier_id' => $identifier->id,
                'identity_type' => $data->type->value,
                'issuing_country' => $country->iso2,
                'last4' => $identifier->identity_last4,
                'is_primary' => $primary,
            ], actor: $actor);

            return $identifier;
        });
    }

    /**
     * Replaces the convert's primary identifier before verification (a
     * registration returned for correction). Same number: nothing changes.
     * A number active elsewhere throws ConvertAlreadyRegistered.
     */
    public function replacePrimary(Convert $convert, IdentifierData $data, User $actor, string $reason): ConvertIdentifier
    {
        ['fingerprint' => $fingerprint] = $this->fingerprint($data);

        return DB::transaction(function () use ($convert, $data, $actor, $reason, $fingerprint): ConvertIdentifier {
            $current = ConvertIdentifier::query()->where('convert_id', $convert->id)->where('is_primary', true)->whereNull('replaced_at')->lockForUpdate()->first();

            if ($current !== null && $current->identity_fingerprint === $fingerprint && $current->identity_type === $data->type) {
                return $current;
            }

            if ($current !== null) {
                $this->retire($convert, $current, $actor, $reason);
            }

            return $this->add($convert, $data, $actor, primary: true);
        });
    }

    /**
     * Frees every active identifier of a convert whose record is closed by a
     * voided shahadah, so the person can be registered again.
     */
    public function releaseAll(Convert $convert, User $actor, string $reason): void
    {
        ConvertIdentifier::query()->where('convert_id', $convert->id)->whereNull('replaced_at')->lockForUpdate()->get()
            ->each(fn (ConvertIdentifier $identifier) => $this->retire($convert, $identifier, $actor, $reason));
    }

    private function retire(Convert $convert, ConvertIdentifier $identifier, User $actor, string $reason): void
    {
        $identifier->is_primary = false;
        $identifier->replaced_at = CarbonImmutable::now();
        $identifier->replaced_reason = $reason;
        $identifier->save();

        $this->audit->record('identity.replaced', $convert, newValues: [
            'identifier_id' => $identifier->id,
            'last4' => $identifier->identity_last4,
            'reason' => $reason,
        ], actor: $actor);
    }

    /**
     * The full number, for holders of identity.reveal. Every reveal is audited.
     */
    public function reveal(ConvertIdentifier $identifier, User $actor): string
    {
        if (! $actor->hasPermission('identity.reveal')) {
            throw new AuthorizationException;
        }

        $this->audit->recordLater('identity.revealed', $identifier->loadMissing('convert')->convert, newValues: [
            'identifier_id' => $identifier->id,
            'identity_type' => $identifier->identity_type->value,
        ], actor: $actor);

        return $identifier->identity_number_encrypted;
    }

    private function issuingCountry(IdentityType $type, ?int $countryId): Country
    {
        $country = $type->issuedBySaudiArabia()
            ? Country::query()->where('iso2', 'SA')->first()
            : ($countryId === null ? null : Country::query()->find($countryId));

        return $country ?? throw IdentityRuleViolation::issuingCountryRequired();
    }
}
