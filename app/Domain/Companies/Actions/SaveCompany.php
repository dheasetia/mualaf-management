<?php

declare(strict_types=1);

namespace App\Domain\Companies\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Companies\Exceptions\CompanyRuleViolation;
use App\Domain\Companies\Models\Company;
use App\Models\User;
use App\Support\Geo\Location;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a company of the current owner: an office its own
 * companies, Central (no tenant context) the national directory.
 */
final readonly class SaveCompany
{
    private const array FIELDS = ['name_ar', 'name_en', 'registration_number', 'phone', 'email', 'website', 'city_id', 'address', 'district', 'postal_code', 'notes'];

    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $data  validated form state
     */
    public function handle(?Company $company, array $data, User $actor): Company
    {
        if ($company !== null && $company->tenant_id !== $this->tenantContext->id()) {
            throw CompanyRuleViolation::notOwned();
        }

        $location = Location::resolve($data['latitude'] ?? null, $data['longitude'] ?? null, $data['google_maps_url'] ?? null, CompanyRuleViolation::invalidMapsUrl(...));

        return DB::transaction(function () use ($company, $data, $actor, $location): Company {
            $company ??= new Company;
            $isNew = ! $company->exists;

            foreach (self::FIELDS as $field) {
                $value = $data[$field] ?? null;
                $company->setAttribute($field, blank($value) ? null : (is_string($value) ? trim($value) : $value));
            }

            $company->latitude = $location['latitude'];
            $company->longitude = $location['longitude'];
            $company->google_maps_url = $location['google_maps_url'];

            if ($isNew) {
                $company->created_by = $actor->id;
            }

            $changes = $company->getDirty();
            $company->save();

            $this->audit->record($isNew ? 'companies.created' : 'companies.updated', $company, newValues: $changes, actor: $actor);

            return $company;
        });
    }
}
