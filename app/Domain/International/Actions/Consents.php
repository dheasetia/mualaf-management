<?php

declare(strict_types=1);

namespace App\Domain\International\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\International\Enums\ConsentMethod;
use App\Domain\International\Enums\ConsentType;
use App\Domain\International\Enums\ReferralStatus;
use App\Domain\International\Enums\SharedField;
use App\Domain\International\Events\ConsentWithdrawn;
use App\Domain\International\Exceptions\InternationalRuleViolation;
use App\Domain\International\Models\ConsentRecord;
use App\Domain\International\Models\InternationalReferral;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Consents of a convert (10 §10.8), recorded by those who record the
 * convert's follow-up. A verbal consent needs a witness. Withdrawing
 * closes every referral that relied on it (reason consent_withdrawn).
 */
final readonly class Consents
{
    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function record(Convert $convert, array $data, User $actor): ConsentRecord
    {
        if (! $actor->can('recordFollowUp', $convert)) {
            throw new AuthorizationException;
        }

        $method = ConsentMethod::from((string) $data['method']);
        $witness = blank($data['witnessed_by'] ?? null) ? null : (int) $data['witnessed_by'];

        if ($method === ConsentMethod::VerbalWitnessed && ($witness === null || $witness === $actor->id)) {
            throw InternationalRuleViolation::witnessRequired();
        }

        $type = ConsentType::from((string) $data['consent_type']);
        $fields = array_values(array_intersect(
            array_map('strval', (array) ($data['fields'] ?? [])),
            array_map(fn (SharedField $field): string => $field->value, SharedField::cases()),
        ));

        if ($type === ConsentType::InternationalSharing && $fields === []) {
            throw InternationalRuleViolation::scopeRequired();
        }

        $consent = new ConsentRecord;
        $consent->convert_id = $convert->id;
        $consent->consent_type = $type;
        $consent->scope = array_filter([
            'fields' => $fields,
            'destination_country_id' => blank($data['destination_country_id'] ?? null) ? null : (int) $data['destination_country_id'],
        ], fn (mixed $value): bool => $value !== null && $value !== []);
        $consent->given_at = blank($data['given_at'] ?? null) ? CarbonImmutable::now() : CarbonImmutable::parse((string) $data['given_at']);
        $consent->method = $method;
        $consent->witnessed_by = $witness;
        $consent->expires_at = blank($data['expires_at'] ?? null) ? null : CarbonImmutable::parse((string) $data['expires_at']);
        $consent->recorded_by = $actor->id;
        $consent->save();

        $this->audit->record('consent.recorded', $convert, newValues: ['consent_id' => $consent->id, 'type' => $type->value, 'scope' => $consent->scope], actor: $actor);

        return $consent;
    }

    public function withdraw(ConsentRecord $consent, ?string $reason, User $actor): ConsentRecord
    {
        $convert = Convert::query()->findOrFail($consent->convert_id);

        if (! $actor->can('recordFollowUp', $convert)) {
            throw new AuthorizationException;
        }

        if ($consent->withdrawn_at !== null) {
            throw InternationalRuleViolation::alreadyWithdrawn();
        }

        $reason = blank($reason) ? throw InternationalRuleViolation::reasonRequired() : trim((string) $reason);

        return DB::transaction(function () use ($consent, $convert, $reason, $actor): ConsentRecord {
            $consent->withdrawn_at = CarbonImmutable::now();
            $consent->withdrawn_reason = $reason;
            $consent->save();

            $closed = InternationalReferral::query()->where('consent_record_id', $consent->id)
                ->whereNotIn('status', [ReferralStatus::Closed, ReferralStatus::Declined])
                ->update(['status' => ReferralStatus::Closed, 'closed_at' => CarbonImmutable::now(), 'close_reason' => 'consent_withdrawn', 'updated_at' => now()]);

            $this->audit->record('consent.withdrawn', $convert, newValues: ['consent_id' => $consent->id, 'reason' => $reason, 'referrals_closed' => $closed], actor: $actor);
            ConsentWithdrawn::dispatch($consent->id, $convert->id, $consent->tenant_id, $closed, $actor->id);

            return $consent;
        });
    }

    /** The newest active consent of this type (not withdrawn, not expired). */
    public function activeOf(int $convertId, ConsentType $type): ?ConsentRecord
    {
        return ConsentRecord::query()
            ->where('convert_id', $convertId)
            ->where('consent_type', $type)
            ->whereNull('withdrawn_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now()))
            ->latest('given_at')
            ->first();
    }

    /**
     * The active international-sharing consent covering these fields and,
     * when the consent names one, this destination.
     *
     * @param  list<string>  $fields
     */
    public function covering(int $convertId, array $fields, int $countryId): ?ConsentRecord
    {
        return ConsentRecord::query()
            ->where('convert_id', $convertId)
            ->where('consent_type', ConsentType::InternationalSharing)
            ->whereNull('withdrawn_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now()))
            ->latest('given_at')
            ->get()
            ->first(fn (ConsentRecord $consent): bool => array_diff($fields, $consent->fields()) === []
                && (($consent->scope['destination_country_id'] ?? null) === null || $consent->scope['destination_country_id'] === $countryId));
    }
}
