<?php

declare(strict_types=1);

namespace App\Domain\Companies\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Companies\Exceptions\CompanyRuleViolation;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Scopes\CompanyScope;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Central (master.manage) moves an office company to the national
 * directory. When the directory already has a company with the same
 * registration number, the office company is merged into it instead: every
 * reference moves to the national company and the office copy is removed.
 */
final readonly class PromoteCompany
{
    /** Tables referring to companies. */
    private const array REFERENCES = [
        'prospects' => 'company_id',
        'convert_employments' => 'company_id',
        'convert_contact_persons' => 'company_id',
    ];

    public function __construct(private AuditLogger $audit) {}

    public function handle(Company $company, User $actor): Company
    {
        if (! $actor->hasPermission('master.manage')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($company, $actor): Company {
            $locked = Company::query()->withoutGlobalScope(CompanyScope::class)->lockForUpdate()->findOrFail($company->id);

            if ($locked->isNational()) {
                throw CompanyRuleViolation::alreadyNational();
            }

            $existing = $locked->registration_number === null ? null : Company::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->whereNull('tenant_id')
                ->where('registration_number', $locked->registration_number)
                ->first();

            if ($existing === null) {
                $fromTenantId = $locked->tenant_id;
                $locked->makeNational();
                $this->audit->record('companies.promoted', $locked, newValues: ['from_tenant_id' => $fromTenantId], actor: $actor);

                return $locked;
            }

            foreach (self::REFERENCES as $table => $column) {
                DB::table($table)->where($column, $locked->id)->update([$column => $existing->id]);
            }

            $this->audit->record('companies.merged', $existing, newValues: [
                'merged_company_id' => $locked->id,
                'from_tenant_id' => $locked->tenant_id,
            ], actor: $actor);
            DB::table('companies')->where('id', $locked->id)->delete();

            return $existing;
        });
    }
}
