<?php

declare(strict_types=1);

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContactPerson;
use App\Domain\Converts\Models\ConvertResidence;
use App\Domain\Converts\Services\IdentityNumber;
use App\Domain\Dai\Models\Dai;
use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Actions\VerifyShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Data\ShahadahWitnessData;
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
            witnesses: shahadahWitnesses(),
        ), grantRole(memberOf(Tenant::query()->findOrFail(app(TenantContext::class)->requireId())), 'registration_officer', Tenant::query()->findOrFail(app(TenantContext::class)->requireId())));

        return Convert::query()->findOrFail($registration->convert_id);
    });

    return compact('convert', 'fromHead', 'toHead', 'fromDai', 'toDai');
}

/**
 * A convert registered in $tenant; the shahadah is verified unless told not to.
 */
function documentsConvert(Tenant $tenant, Gender $gender = Gender::Male, bool $verified = true, string $identity = '277001122'): Convert
{
    return inTenant($tenant, function () use ($tenant, $gender, $verified, $identity): Convert {
        $registration = app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
            profile: new ConvertProfileData('Daniel Mendoza', $gender, Country::factory()->create()->id, islamicName: 'Dawud'),
            identity: new IdentifierData(IdentityType::Iqamah, saudiId($identity)),
            shahadahDate: CarbonImmutable::parse('2026-11-01'),
            location: 'Center hall',
            primaryDaiId: Dai::factory()->create()->id,
            witnesses: shahadahWitnesses(),
        ), grantRole(memberOf($tenant), 'registration_officer', $tenant));

        if ($verified) {
            app(VerifyShahadah::class)->handle($registration, grantRole(memberOf($tenant), 'shahadah_verifier', $tenant));
        }

        return Convert::query()->findOrFail($registration->convert_id);
    });
}

/** A JPEG carrying an EXIF block with a recognisable marker. */
function jpegWithExif(string $marker): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    $tiff = "II*\x00\x08\x00\x00\x00\x00\x00".$marker;
    $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\x00\x00".$tiff;

    return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
}

/**
 * A convert of $tenant with a residence pickup point and a contact person
 * who agreed to be contacted. Use inside inTenant().
 *
 * @return array{convert: Convert, residence: ConvertResidence, contact: ConvertContactPerson}
 */
function convertWithPickupPoint(array $attributes = []): array
{
    $convert = Convert::factory()->create(['full_name' => 'Daniel Mendoza', 'islamic_name' => 'Dawud', ...$attributes]);
    $author = $convert->created_by;
    $residence = (new ConvertResidence)->forceFill([
        'convert_id' => $convert->id, 'residence_type' => 'company_dormitory', 'name' => 'Camp 4, Block B',
        'latitude' => '26.4207000', 'longitude' => '50.0888000', 'is_pickup_point' => true, 'is_current' => true, 'created_by' => $author,
    ]);
    $residence->save();
    $contact = (new ConvertContactPerson)->forceFill([
        'convert_id' => $convert->id, 'name' => 'Camp supervisor', 'contact_role' => 'supervisor', 'phone_encrypted' => '+966501112233',
        'permission_to_contact' => true, 'disclosure_scope' => 'none', 'is_primary' => true, 'created_by' => $author,
    ]);
    $contact->save();

    return compact('convert', 'residence', 'contact');
}

/**
 * The witnesses every shahadah registration needs (open question #2).
 *
 * @return list<ShahadahWitnessData>
 */
function shahadahWitnesses(): array
{
    return [
        new ShahadahWitnessData('Ahmad Al-Harbi', '0551234001'),
        new ShahadahWitnessData('Khalid Al-Otaibi', '0551234002', identityLast4: '4321', relationship: 'Colleague'),
    ];
}
