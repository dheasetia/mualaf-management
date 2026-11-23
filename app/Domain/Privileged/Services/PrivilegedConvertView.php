<?php

declare(strict_types=1);

namespace App\Domain\Privileged\Services;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContactPerson;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\FollowUp\Models\ConvertFollowUpStatus;
use App\Domain\FollowUp\Models\ConvertLongTermFollowUp;
use App\Domain\Privileged\Enums\PrivilegedScope;
use App\Domain\Privileged\Models\PrivilegedAccessRequest;
use App\Domain\Registration\Services\ShahadahDirectory;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;

/**
 * What an active grant shows of its convert, limited to the granted scopes.
 * Read in the convert's current office; follow-up notes are counted, their
 * content is never shown.
 */
final readonly class PrivilegedConvertView
{
    public function __construct(private TenantContext $tenantContext, private ShahadahDirectory $shahadah, private DaiDirectory $dais) {}

    /** @return array<string, array<string, string|null>> */
    public function sections(PrivilegedAccessRequest $grant): array
    {
        $tenantId = (int) $grant->tenant_id;

        return $this->tenantContext->run($tenantId, function () use ($grant, $tenantId): array {
            $convert = Convert::query()->with('nationality')->findOrFail((int) $grant->resource_id);
            $sections = [];

            if ($grant->allows(PrivilegedScope::Profile)) {
                $sections[PrivilegedScope::Profile->value] = [
                    'full_name' => $convert->full_name,
                    'islamic_name' => $convert->islamic_name,
                    'gender' => $convert->gender->label(),
                    'nationality' => (string) $convert->nationality?->getAttribute('name_ar'),
                    'status' => $convert->status->label(),
                    'office' => (string) Tenant::query()->whereKey($tenantId)->value('name_ar'),
                    'disclosure' => $convert->disclosure_risk_level->value,
                    'primary_dai' => $this->dais->names([$convert->primary_dai_id])[$convert->primary_dai_id] ?? null,
                ];
            }

            if ($grant->allows(PrivilegedScope::Identity)) {
                $sections[PrivilegedScope::Identity->value] = ConvertIdentifier::query()->where('convert_id', $convert->id)->get()
                    ->mapWithKeys(fn (ConvertIdentifier $identifier): array => [$identifier->identity_type->label() => $identifier->identity_number_encrypted])->all();
            }

            if ($grant->allows(PrivilegedScope::Contacts)) {
                $sections[PrivilegedScope::Contacts->value] = ConvertContactPerson::query()->where('convert_id', $convert->id)->get()
                    ->mapWithKeys(fn (ConvertContactPerson $contact): array => [$contact->name => trim(($contact->phone_encrypted ?? '').($contact->permission_to_contact ? '' : ' ('.__('privileged.no_permission_to_contact').')'))])->all();
            }

            if ($grant->allows(PrivilegedScope::Shahadah)) {
                $record = $this->shahadah->verifiedFirst($convert->id);
                $sections[PrivilegedScope::Shahadah->value] = [
                    'date' => $record === null ? null : $record['date']->toDateString(),
                    'location' => $record['location'] ?? null,
                ];
            }

            if ($grant->allows(PrivilegedScope::FollowUps)) {
                $status = ConvertFollowUpStatus::query()->where('convert_id', $convert->id)->latest('created_at')->first();
                $sections[PrivilegedScope::FollowUps->value] = [
                    'status' => $status?->status->label(),
                    'last_contact_at' => $status?->last_contact_at?->toDateString(),
                    'contacts_recorded' => (string) ConvertLongTermFollowUp::query()->where('convert_id', $convert->id)->count(),
                ];
            }

            return $sections;
        });
    }
}
