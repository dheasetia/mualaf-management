<?php

declare(strict_types=1);

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\IdentityNumber;
use App\Domain\Dai\Models\Dai;
use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\RbacSeeder;
use Filament\Facades\Filament;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'TenantIsolation', 'Concurrency');

/**
 * Runs the callback with the given tenant as context.
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function inTenant(Tenant $tenant, Closure $callback): mixed
{
    return app(TenantContext::class)->run($tenant, $callback);
}

function memberOf(Tenant ...$tenants): User
{
    $user = User::factory()->create();

    foreach ($tenants as $tenant) {
        UserTenantMembership::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => MembershipStatus::Active,
        ]);
    }

    return $user;
}

function seedRbac(): void
{
    test()->seed(RbacSeeder::class);
}

/**
 * Gives the user a system role (seeded by RbacSeeder) as the system would at bootstrap.
 */
function grantRole(User $user, string $roleCode, ?Tenant $tenant = null): User
{
    app(AssignRole::class)->handle($user, Role::query()->where('code', $roleCode)->sole(), $tenant, null);

    return $user;
}

/**
 * Enables an authenticator app, so panels that require MFA let the user in.
 */
function withAuthenticator(User $user): User
{
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    return $user;
}

/** Livewire tests skip panel middleware: sign in and set what SetTenantContext would. */
function inCenterPanel(Tenant $tenant, User $user): User
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('center'));
    Filament::setTenant($tenant);
    app(TenantContext::class)->set($tenant);

    return $user;
}

/**
 * A valid Saudi ID number (Luhn) from its first nine digits.
 */
function saudiId(string $firstNine): string
{
    foreach (range(0, 9) as $check) {
        if (IdentityNumber::passesLuhn($firstNine.$check)) {
            return $firstNine.$check;
        }
    }

    throw new LogicException('unreachable');
}

/**
 * Asserts that the action breaks a business rule on the given form field.
 */
function expectRuleViolation(Closure $action, string $field): void
{
    expect($action)->toThrow(fn (RuleViolation $violation) => expect($violation->field)->toBe($field));
}

/**
 * A convert registered in $from (Iqamah $iqamah) and a Head of Dawah plus an
 * active da'i in each office, for transfer tests.
 *
 * @return array{convert: Convert, fromHead: User, toHead: User, fromDai: Dai, toDai: Dai}
 */
function transferSetup(Tenant $from, Tenant $to, string $iqamah): array
{
    Country::query()->firstOrCreate(['iso2' => 'SA'], ['iso3' => 'SAU', 'name_ar' => 'السعودية', 'name_en' => 'Saudi Arabia']);
    $fromHead = withAuthenticator(grantRole(memberOf($from), 'head_of_dawah', $from));
    $toHead = withAuthenticator(grantRole(memberOf($to), 'head_of_dawah', $to));
    $fromDai = inTenant($from, fn () => Dai::factory()->create(['user_id' => $fromHead->id]));
    $toDai = inTenant($to, fn () => Dai::factory()->create(['user_id' => $toHead->id]));

    $convert = inTenant($from, function () use ($fromDai, $iqamah): Convert {
        $registration = app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
            profile: new ConvertProfileData('Abdullah Reyes', Gender::Male, Country::factory()->create()->id),
            identity: new IdentifierData(IdentityType::Iqamah, $iqamah),
            shahadahDate: CarbonImmutable::today()->subMonth(),
            location: 'Center hall',
            primaryDaiId: $fromDai->id,
        ), grantRole(memberOf(Tenant::query()->findOrFail(app(TenantContext::class)->requireId())), 'registration_officer', Tenant::query()->findOrFail(app(TenantContext::class)->requireId())));

        return Convert::query()->findOrFail($registration->convert_id);
    });

    return compact('convert', 'fromHead', 'toHead', 'fromDai', 'toDai');
}
