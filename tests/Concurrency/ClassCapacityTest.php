<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Guidance\Actions\ManageEnrollments;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Exceptions\RuleViolation;

// 09 §9.3: capacity is counted on the locked class row. This checks the
// count under the lock; true parallel requests need separate connections.
it('never fills a class beyond capacity', function (): void {
    seedRbac();
    $tenant = Tenant::factory()->active()->create();
    $coordinator = grantRole(memberOf($tenant), 'guidance_coordinator', $tenant);

    inTenant($tenant, function () use ($coordinator): void {
        $program = GuidanceProgram::factory()->create();
        $class = GuidanceClass::factory()->create(['program_id' => $program->id, 'capacity' => 2]);
        $results = [];

        foreach (range(1, 4) as $attempt) {
            try {
                app(ManageEnrollments::class)->enroll(Convert::factory()->create()->id, $program->id, $class->id, $coordinator);
                $results[] = 'enrolled';
            } catch (RuleViolation) {
                $results[] = 'full';
            }
        }

        expect($results)->toBe(['enrolled', 'enrolled', 'full', 'full'])
            ->and(Enrollment::query()->where('class_id', $class->id)->count())->toBe(2);
    });
});
