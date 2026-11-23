<?php

declare(strict_types=1);

use App\Domain\Pilgrimage\Actions\RegistrationWorkflow;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;

// 10 §10.2: the quota is counted on the locked program row. This checks the
// count under the lock; true parallel requests need separate connections.
it('never approves more pilgrims than the program has seats', function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $tenant = Tenant::factory()->active()->create();
    $officer = grantRole(memberOf($tenant), 'pilgrimage_officer', $tenant);
    $approver = grantRole(memberOf($tenant), 'approver', $tenant);
    $program = inTenant($tenant, fn () => PilgrimageProgram::factory()->create(['capacity' => 2]));
    $converts = collect(['277003311', '277003322', '277003333', '277003344'])->map(fn (string $identity) => documentsConvert($tenant, identity: $identity));

    inTenant($tenant, function () use ($program, $converts, $officer, $approver): void {
        $statuses = $converts->map(function ($convert) use ($program, $officer, $approver): RegistrationStatus {
            $workflow = app(RegistrationWorkflow::class);
            $registration = $workflow->checkEligibility($workflow->register($program, $convert, $officer), $officer);

            return $workflow->approve($registration, $approver)->status;
        })->all();

        expect($statuses)->toBe([RegistrationStatus::Approved, RegistrationStatus::Approved, RegistrationStatus::Waitlisted, RegistrationStatus::Waitlisted])
            ->and(PilgrimageRegistration::query()->where('status', RegistrationStatus::Approved)->count())->toBe(2);
    });
});
