<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\IdentityNumber;
use App\Domain\Dai\Actions\CreateDai;
use App\Domain\Dai\Data\DaiData;
use App\Domain\Dai\Data\FollowUpData;
use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\EmploymentStatus;
use App\Domain\Dai\Enums\FollowUpType;
use App\Domain\Dai\Enums\LanguageProficiency;
use App\Domain\Dai\Models\Dai;
use App\Domain\Guidance\Actions\ManageClasses;
use App\Domain\Guidance\Actions\ManageEnrollments;
use App\Domain\Guidance\Enums\ClassStatus;
use App\Domain\Guidance\Enums\TeacherRole;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Domain\Prospects\Actions\ChangeProspectStatus;
use App\Domain\Prospects\Actions\CreateProspect;
use App\Domain\Prospects\Actions\RecordProspectFollowUp;
use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Tenancy\Actions\ChangeTenantStatus;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Domain\Tenancy\Actions\GrantTenantMembership;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Synthetic local data only. Never run against staging or production data.
 */
final class DevelopmentSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> */
    private const array TENANTS = [
        'DMM' => ['المركز الإسلامي بالدمام', 'Islamic Center Dammam'],
        'RUH' => ['المركز الإسلامي بالرياض', 'Islamic Center Riyadh'],
        'JED' => ['المركز الإسلامي بجدة', 'Islamic Center Jeddah'],
    ];

    public function run(
        CreateTenant $createTenant,
        ChangeTenantStatus $changeStatus,
        GrantTenantMembership $grant,
        AssignRole $assignRole,
        CreateDai $createDai,
        TenantContext $tenantContext,
    ): void {
        $password = config('app.dev_seed_password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set DEV_SEED_PASSWORD (12+ characters) in .env before seeding development data.');
        }

        $this->call([PlatformMasterSeeder::class, RbacSeeder::class, GuidanceProgramSeeder::class, CertificateTemplateSeeder::class, DocumentTemplateSeeder::class]);

        $user = User::query()->firstOrCreate(
            ['email' => 'dev.admin@muallaf.test'],
            ['name' => 'Dev Admin', 'password' => $password],
        );

        foreach (self::TENANTS as $code => [$nameAr, $nameEn]) {
            $existing = Tenant::query()->where('code', $code)->first();

            if ($existing !== null) {
                $this->seedDaisOnce($createDai, $tenantContext, $existing, $user);

                continue;
            }

            $city = City::query()->where('code', $code)->firstOrFail();

            $tenant = $createTenant->handle([
                'code' => $code,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'region_id' => $city->region_id,
                'city_id' => $city->id,
            ]);

            $changeStatus->handle($tenant, TenantStatus::Active);
            $grant->handle($user, $tenant);
            $assignRole->handle($user, Role::query()->where('code', 'center_admin')->sole(), $tenant, null);

            // One synthetic user per office role, e.g. dai.dmm@muallaf.test.
            foreach (Role::query()->where('scope_type', RoleScope::Tenant)->where('code', '!=', 'center_admin')->get() as $role) {
                $staff = User::query()->create([
                    'name' => $role->name_en.' '.$code,
                    'email' => sprintf('%s.%s@muallaf.test', str_replace('_', '-', $role->code), strtolower($code)),
                    'password' => $password,
                ]);
                $grant->handle($staff, $tenant);
                $assignRole->handle($staff, $role, $tenant, null);
            }

            $this->seedDaisOnce($createDai, $tenantContext, $tenant, $user);
        }

        if (! $user->roleAssignments()->whereNull('tenant_id')->exists()) {
            $assignRole->handle($user, Role::query()->where('code', 'super_admin')->sole(), null, null);
        }
    }

    private function seedDaisOnce(CreateDai $createDai, TenantContext $tenantContext, Tenant $tenant, User $actor): void
    {
        $tenantContext->run($tenant, function () use ($createDai, $tenant, $actor): void {
            if (! Dai::query()->exists()) {
                $this->seedDais($createDai, $tenant->code, $actor);
            }

            if (! Prospect::query()->exists()) {
                $this->seedProspects($tenant->code);
            }

            if (! Convert::query()->exists()) {
                $this->seedConvert($tenant->code);
            }

            if (! GuidanceClass::query()->exists()) {
                $this->seedClass($tenant->code);
            }
        });
    }

    /**
     * A head of dawah supervising three da'i; the first two are linked to the
     * synthetic Head of Dawah and Da'i users of the office.
     */
    private function seedDais(CreateDai $createDai, string $code, User $actor): void
    {
        $language = fn (string $languageCode): int => Language::query()->where('code', $languageCode)->value('id');
        $userId = fn (string $role): ?int => User::query()
            ->where('email', sprintf('%s.%s@muallaf.test', $role, strtolower($code)))->value('id');

        $head = $createDai->handle(new DaiData(
            employeeNumber: $code.'-D001',
            fullName: 'Ahmad Al-Harbi',
            fullNameAr: 'أحمد الحربي',
            gender: Gender::Male,
            employmentStatus: EmploymentStatus::FullTime,
            specialties: ['Comparative religion'],
            userId: $userId('head-of-dawah'),
            languages: [$language('ar') => LanguageProficiency::Native, $language('en') => LanguageProficiency::Fluent],
        ), $actor);

        $team = [
            ['D002', 'Yusuf Reyes', 'يوسف رييس', Gender::Male, $userId('dai'), ['tl' => LanguageProficiency::Native, 'en' => LanguageProficiency::Fluent]],
            ['D003', 'Bilal Rahman', 'بلال رحمن', Gender::Male, null, ['bn' => LanguageProficiency::Native, 'ur' => LanguageProficiency::Working]],
            ['D004', 'Maryam Dewi', 'مريم ديوي', Gender::Female, null, ['id' => LanguageProficiency::Native, 'ar' => LanguageProficiency::Working]],
        ];

        foreach ($team as [$number, $name, $nameAr, $gender, $linkedUser, $languages]) {
            $createDai->handle(new DaiData(
                employeeNumber: $code.'-'.$number,
                fullName: $name,
                fullNameAr: $nameAr,
                gender: $gender,
                employmentStatus: EmploymentStatus::FullTime,
                supervisorDaiId: $head->id,
                userId: $linkedUser,
                languages: collect($languages)->mapWithKeys(fn (LanguageProficiency $level, string $languageCode): array => [$language($languageCode) => $level])->all(),
            ), $actor);
        }
    }

    /**
     * Prospects of the synthetic Da'i user (registered by them) at different
     * stages, plus two held by other da'i of the office.
     */
    private function seedProspects(string $code): void
    {
        $user = fn (string $role): User => User::query()
            ->where('email', sprintf('%s.%s@muallaf.test', $role, strtolower($code)))->firstOrFail();
        $daiUser = $user('dai');
        $head = $user('head-of-dawah');
        $daiId = fn (string $number): int => (int) Dai::query()->where('employee_number', $code.'-'.$number)->value('id');

        $people = [
            ['Mark Villanueva', Gender::Male, ProspectSource::DaiContact, '0551000001', null, ProspectStatus::LearningIslam],
            ['Grace Lim', Gender::Female, ProspectSource::ReferralConvert, '0551000002', null, ProspectStatus::Contacted],
            ['Ravi Kumar', Gender::Male, ProspectSource::WalkIn, '0551000003', null, ProspectStatus::New],
            ['Arjun Nair', Gender::Male, ProspectSource::Event, null, 'D003', ProspectStatus::New],
            ['Siti Rahayu', Gender::Female, ProspectSource::ReferralCompany, null, 'D004', ProspectStatus::New],
        ];

        foreach ($people as [$name, $gender, $source, $phone, $daiNumber, $target]) {
            $prospect = $daiNumber === null
                ? app(CreateProspect::class)->handle(new ProspectData($name, $gender, $source, phone: $phone), $daiUser)
                : app(CreateProspect::class)->handle(new ProspectData($name, $gender, $source, primaryDaiId: $daiId($daiNumber)), $head);

            if ($target === ProspectStatus::New) {
                continue;
            }

            app(RecordProspectFollowUp::class)->handle($prospect, new FollowUpData(
                FollowUpType::Discussion,
                ContactMethod::InPerson,
                CarbonImmutable::now()->subDays(3),
                'Introduced the basics of Islam and answered questions about prayer.',
                nextFollowUpAt: CarbonImmutable::now()->addDays(4)->setTime(18, 0),
            ), $daiUser);

            foreach ([ProspectStatus::ActiveFollowUp, ProspectStatus::LearningIslam] as $step) {
                if ($prospect->refresh()->status === $target) {
                    break;
                }

                app(ChangeProspectStatus::class)->handle($prospect, $step, $daiUser);
            }
        }
    }

    /**
     * One walk-in convert per office, registered by the synthetic Registration
     * Officer and left waiting for verification. The Iqamah number is synthetic.
     */
    private function seedConvert(string $code): void
    {
        $registrar = User::query()->where('email', sprintf('registration-officer.%s@muallaf.test', strtolower($code)))->firstOrFail();
        $prefix = '29'.str_pad((string) (crc32($code) % 10_000_000), 7, '0', STR_PAD_LEFT);
        $iqamah = collect(range(0, 9))->map(fn (int $check): string => $prefix.$check)->first(fn (string $number): bool => IdentityNumber::passesLuhn($number));

        app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
            profile: new ConvertProfileData('Daniel Mendoza', Gender::Male, (int) Country::query()->where('iso2', 'PH')->value('id'), islamicName: 'Dawud'),
            identity: new IdentifierData(IdentityType::Iqamah, (string) $iqamah),
            shahadahDate: CarbonImmutable::now()->subDays(2),
            location: 'Center hall',
            primaryDaiId: (int) Dai::query()->where('employee_number', $code.'-D002')->value('id'),
            phone: '0552000000',
        ), $registrar);
    }

    /**
     * An open Tagalog class of the national program, taught by the synthetic
     * Teacher, with the office's sample convert enrolled.
     */
    private function seedClass(string $code): void
    {
        $user = fn (string $role): User => User::query()->where('email', sprintf('%s.%s@muallaf.test', $role, strtolower($code)))->firstOrFail();
        $coordinator = $user('guidance-coordinator');
        $program = GuidanceProgram::query()->whereNull('tenant_id')->where('code', 'NEW-MUSLIM')->firstOrFail();

        $class = app(ManageClasses::class)->save(null, [
            'program_id' => $program->id,
            'code' => $code.'-TL-01',
            'name' => 'Tagalog foundation class',
            'language_id' => Language::query()->where('code', 'tl')->value('id'),
            'gender_policy' => 'male',
            'capacity' => 20,
            'starts_on' => CarbonImmutable::today()->subWeek()->toDateString(),
            'ends_on' => CarbonImmutable::today()->addMonths(3)->toDateString(),
        ], [['day_of_week' => 5, 'start_time' => '16:00', 'end_time' => '17:30']], $coordinator);

        app(ManageClasses::class)->changeStatus($class, ClassStatus::Open, $coordinator);
        app(ManageClasses::class)->assignTeacher($class, $user('teacher')->id, TeacherRole::LeadTeacher, $coordinator);

        $convert = Convert::query()->first();

        if ($convert !== null) {
            app(ManageEnrollments::class)->enroll($convert->id, $program->id, $class->id, $coordinator);
        }
    }
}
