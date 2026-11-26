<?php

declare(strict_types=1);

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Pages\Dashboard;
use App\Filament\Center\Widgets\AwaitingVerificationWidget;
use App\Filament\Center\Widgets\MyFollowUpsWidget;
use App\Filament\Center\Widgets\OfficeStatsWidget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
});

it('shows quick actions only for what the user may do', function (): void {
    inCenterPanel($this->tenant, grantRole(memberOf($this->tenant), 'registration_officer', $this->tenant));

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertActionVisible('registerShahadah')
        ->assertActionHidden('newProspect');

    inCenterPanel($this->tenant, grantRole(memberOf($this->tenant), 'dai', $this->tenant));

    Livewire::test(Dashboard::class)
        ->assertActionHidden('registerShahadah')
        ->assertActionVisible('followUps');
});

it('lists the da\'i\'s own follow-ups that are overdue or due today', function (): void {
    $user = inCenterPanel($this->tenant, grantRole(memberOf($this->tenant), 'dai', $this->tenant));
    [$overdue, $today, $tomorrow, $done, $someoneElses] = inTenant($this->tenant, function () use ($user): array {
        $mine = Dai::factory()->create(['user_id' => $user->id]);
        $other = Dai::factory()->create();
        $convert = Convert::factory()->create();
        $schedule = fn (Dai $dai, CarbonImmutable $at, ScheduleStatus $status = ScheduleStatus::Scheduled): ConvertFollowUpSchedule => ConvertFollowUpSchedule::factory()
            ->create(['dai_id' => $dai->id, 'convert_id' => $convert->id, 'scheduled_at' => $at->utc(), 'status' => $status]);
        $now = CarbonImmutable::now((string) config('app.display_timezone'));

        return [
            $schedule($mine, $now->subDays(2)),
            $schedule($mine, $now->endOfDay()->subMinute()),
            $schedule($mine, $now->addDay()->endOfDay()),
            $schedule($mine, $now->subDay(), ScheduleStatus::Completed),
            $schedule($other, $now->subDay()),
        ];
    });

    expect(MyFollowUpsWidget::canView())->toBeTrue()
        ->and(AwaitingVerificationWidget::canView())->toBeFalse();

    Livewire::test(MyFollowUpsWidget::class)
        ->assertCanSeeTableRecords([$overdue, $today])
        ->assertCanNotSeeTableRecords([$tomorrow, $done, $someoneElses]);
});

it('hides the follow-up list from users without a da\'i record', function (): void {
    inCenterPanel($this->tenant, grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant));

    expect(MyFollowUpsWidget::canView())->toBeFalse();
});

it('lists registrations awaiting verification, without the verifier\'s own', function (): void {
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $registrar = grantRole(memberOf($this->tenant), 'registration_officer', $this->tenant);
    $verifier = grantRole(grantRole(memberOf($this->tenant), 'shahadah_verifier', $this->tenant), 'registration_officer', $this->tenant);
    $dai = inTenant($this->tenant, fn () => Dai::factory()->create());
    $register = fn (User $actor, string $iqamah): ConvertRegistration => inTenant($this->tenant, fn () => app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
        profile: new ConvertProfileData('Maria Cruz', Gender::Female, Country::factory()->create()->id),
        identity: new IdentifierData(IdentityType::Iqamah, $iqamah),
        shahadahDate: CarbonImmutable::today(),
        location: 'Center hall',
        primaryDaiId: $dai->id,
        witnesses: shahadahWitnesses(),
    ), $actor));
    $byRegistrar = $register($registrar, saudiId('223344556'));
    $byVerifier = $register($verifier, saudiId('223344557'));

    inCenterPanel($this->tenant, $verifier);

    expect(AwaitingVerificationWidget::canView())->toBeTrue();
    Livewire::test(AwaitingVerificationWidget::class)
        ->assertCanSeeTableRecords([$byRegistrar])
        ->assertCanNotSeeTableRecords([$byVerifier]);
});

it('shows office figures only to holders of reports.view_office', function (): void {
    inCenterPanel($this->tenant, grantRole(memberOf($this->tenant), 'dai', $this->tenant));
    expect(OfficeStatsWidget::canView())->toBeFalse();

    inCenterPanel($this->tenant, grantRole(memberOf($this->tenant), 'local_auditor', $this->tenant));
    expect(OfficeStatsWidget::canView())->toBeTrue();
    Livewire::test(OfficeStatsWidget::class)->assertSuccessful()->assertSee(__('dashboard.stats.converts_active'));
});
