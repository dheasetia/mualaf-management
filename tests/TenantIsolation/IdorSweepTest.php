<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/*
 * 13 §13.5/§13.6 (IDOR): no Center page that opens a record by id shows a
 * record of another office, even to a user holding every office role. The
 * sweep covers every resource page with a {record} parameter, so new
 * resources are checked automatically.
 */
it('never opens another office\'s record from a record page', function (): void {
    seedRbac();
    $mine = Tenant::factory()->active()->create(['code' => 'DMM']);
    $theirs = Tenant::factory()->active()->create(['code' => 'RUH']);
    $user = memberOf($mine);

    foreach (['center_admin', 'head_of_dawah', 'local_auditor', 'certificate_officer', 'administrative_officer', 'transportation_officer', 'pilgrimage_officer', 'guidance_coordinator'] as $role) {
        grantRole($user, $role, $mine);
    }

    withAuthenticator($user);
    Filament::setCurrentPanel(Filament::getPanel('center'));
    $targets = [];

    // Records first: after a request Filament keeps the visited tenant, which would tag later records.
    foreach (Filament::getPanel('center')->getResources() as $resource) {
        /** @var class-string<resource> $resource */
        $model = $resource::getModel();
        $recordPages = array_filter(array_keys($resource::getPages()), fn (string $page): bool => in_array($page, ['view', 'edit', 'sheet'], true));

        if ($recordPages !== [] && in_array(HasFactory::class, class_uses_recursive($model), true)) {
            // Global-or-office models (programs, companies, materials) default to national rows, which every office may see.
            $officeOwned = ! in_array(BelongsToTenant::class, class_uses_recursive($model), true) && Schema::hasColumn((new $model)->getTable(), 'tenant_id');
            $targets[] = [$resource, $recordPages, inTenant($theirs, fn (): Model => $model::factory()->create($officeOwned ? ['tenant_id' => $theirs->id] : []))];
        }
    }

    $checked = [];
    $failures = [];

    foreach ($targets as [$resource, $recordPages, $record]) {
        foreach ($recordPages as $page) {
            $url = str_replace('/RUH/', '/DMM/', $resource::getUrl($page, ['record' => $record, 'tenant' => $theirs]));
            $status = $this->actingAs($user)->get($url)->getStatusCode();
            $checked[] = $resource.'::'.$page;

            if ($status === 200) {
                $failures[] = "{$resource}::{$page} opened another office's record ({$url})";
            }
        }
    }

    expect($failures)->toBe([])
        ->and(count($checked))->toBeGreaterThan(8);
})->group('tenant-isolation');
