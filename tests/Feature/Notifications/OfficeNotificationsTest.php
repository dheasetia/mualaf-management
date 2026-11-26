<?php

declare(strict_types=1);

use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Dai\Models\Dai;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\International\Actions\Consents;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Actions\AcceptConvertTransfer;
use App\Domain\Transfers\Actions\RequestConvertTransfer;
use App\Domain\Transfers\Actions\ReviewConvertTransfer;
use App\Models\User;
use Database\Seeders\FollowUpScheduleTemplateSeeder;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Notifications\DatabaseNotification;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->kinds = fn (User $user, Tenant $tenant): array => DatabaseNotification::query()->where('notifiable_id', $user->id)->where('tenant_id', $tenant->id)
        ->get()->map(fn (DatabaseNotification $notification): string => (string) $notification->data['kind'])->all();
});

it('tells the office\'s verifiers about a new shahadah, without personal details', function (): void {
    $verifier = grantRole(memberOf($this->dammam), 'shahadah_verifier', $this->dammam);
    $elsewhere = grantRole(memberOf($this->riyadh), 'shahadah_verifier', $this->riyadh);
    $registrar = grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam);
    $iqamah = saudiId('288112233');

    documentsConvert($this->dammam, verified: false, identity: '288112233');

    expect(($this->kinds)($verifier, $this->dammam))->toBe(['shahadah_recorded'])
        ->and(($this->kinds)($elsewhere, $this->riyadh))->toBe([])
        ->and(($this->kinds)($registrar, $this->dammam))->toBe([]);

    $payload = json_encode(DatabaseNotification::query()->where('notifiable_id', $verifier->id)->sole()->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    expect($payload)->not->toContain('Daniel Mendoza')->not->toContain($iqamah)->toContain('/center/DMM/');
});

it('follows a convert transfer: request, approval and completion', function (): void {
    $iqamah = saudiId('233445511');
    extract(transferSetup($this->dammam, $this->riyadh, $iqamah));

    $request = inTenant($this->riyadh, fn () => app(RequestConvertTransfer::class)->handle(new IdentifierData(IdentityType::Iqamah, $iqamah), 'Moved', $toHead));
    expect(($this->kinds)($fromHead, $this->dammam))->toContain('transfer_requested');

    inTenant($this->dammam, fn () => app(ReviewConvertTransfer::class)->approve($request, $fromHead));
    expect(($this->kinds)($toHead, $this->riyadh))->toContain('transfer_approved');

    inTenant($this->riyadh, fn () => app(AcceptConvertTransfer::class)->handle($request, $toDai->id, $toHead));
    expect(($this->kinds)($fromHead, $this->dammam))->toContain('transfer_completed_out')
        ->and(($this->kinds)($toHead, $this->riyadh))->toContain('transfer_completed_in')->toContain('transfer_new_convert');
});

it('tells the primary da\'i when a consent is withdrawn', function (): void {
    $head = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    $daiUser = grantRole(memberOf($this->dammam), 'dai', $this->dammam);
    $convert = documentsConvert($this->dammam);
    inTenant($this->dammam, function () use ($convert, $daiUser, $head): void {
        $dai = Dai::factory()->create(['user_id' => $daiUser->id]);
        $convert->forceFill(['primary_dai_id' => $dai->id])->saveQuietly();
        $consent = app(Consents::class)->record($convert, ['consent_type' => 'international_sharing', 'fields' => ['name'], 'method' => 'written'], $head);
        app(Consents::class)->withdraw($consent, 'Changed mind', $head);
    });

    expect(($this->kinds)($daiUser, $this->dammam))->toBe(['consent_withdrawn']);
});

it('reminds each da\'i of overdue follow-ups daily and escalates week-old ones once', function (): void {
    $this->seed(FollowUpScheduleTemplateSeeder::class);
    $head = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    $daiUser = grantRole(memberOf($this->dammam), 'dai', $this->dammam);
    $convert = documentsConvert($this->dammam);
    inTenant($this->dammam, function () use ($convert, $daiUser): void {
        $dai = Dai::factory()->create(['user_id' => $daiUser->id]);
        ConvertFollowUpSchedule::query()->where('convert_id', $convert->id)->update(['dai_id' => $dai->id]);
    });

    $this->travel(20)->days();
    $this->artisan('followups:notify-overdue')->assertSuccessful();
    $this->artisan('followups:notify-overdue')->assertSuccessful();

    $reminder = DatabaseNotification::query()->where('notifiable_id', $daiUser->id)->latest()->first();
    expect(($this->kinds)($daiUser, $this->dammam))->toBe(['follow_ups_overdue', 'follow_ups_overdue'])
        ->and($reminder?->data['body'])->toContain('1')
        ->and(($this->kinds)($head, $this->dammam))->toBe(['follow_ups_escalated']);
});

it('shows each office only its own notifications', function (): void {
    $user = grantRole(grantRole(memberOf($this->dammam, $this->riyadh), 'shahadah_verifier', $this->dammam), 'shahadah_verifier', $this->riyadh);
    documentsConvert($this->dammam, verified: false, identity: '288114455');

    expect(inTenant($this->dammam, fn () => $user->notifications()->count()))->toBe(1)
        ->and(inTenant($this->riyadh, fn () => $user->notifications()->count()))->toBe(0)
        ->and($user->notifications()->count())->toBe(0);
});

it('shows the office\'s alerts in the panel bell', function (): void {
    $verifier = grantRole(memberOf($this->dammam), 'shahadah_verifier', $this->dammam);
    documentsConvert($this->dammam, verified: false, identity: '288116677');
    inCenterPanel($this->dammam, $verifier);

    $bell = Livewire\Livewire::test(DatabaseNotifications::class)->instance();

    expect($bell->getUnreadNotificationsCount())->toBe(1)
        ->and($bell->getNotifications()->first()?->data['title'])->toBe(__('notifications.shahadah_recorded.title', [], 'ar'));
});
