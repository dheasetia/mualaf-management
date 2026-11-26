<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Notifications\Data\WhatsAppTemplateMessage;
use App\Domain\Notifications\Jobs\SendWhatsAppMessage;
use App\Domain\Notifications\Models\IntegrationLog;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Pages\OfficeProfile;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    config([
        'services.whatsapp.enabled' => true,
        'services.whatsapp.base_url' => 'https://graph.test',
        'services.whatsapp.api_version' => 'v21.0',
        'services.whatsapp.phone_number_id' => '1234567890',
        'services.whatsapp.access_token' => 'secret-token',
        'services.whatsapp.template' => 'office_alert',
    ]);
    $this->optedIn = fn (string $locale = 'ar'): User => tap(grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam), function (User $user) use ($locale): void {
        $user->forceFill(['phone_encrypted' => '+966551234567', 'whatsapp_opt_in_at' => now(), 'locale' => $locale])->save();
    });
    $this->alert = fn (): OfficeAlert => new OfficeAlert('transfer_requested', $this->dammam->id, url: '/center/DMM/transfers');
});

it('queues a WhatsApp copy only for users who opted in, and only when WhatsApp is configured', function (): void {
    Queue::fake();
    $optedIn = ($this->optedIn)();
    $notOptedIn = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
    $notOptedIn->forceFill(['phone_encrypted' => '+966551234568'])->save();

    $optedIn->notify(($this->alert)());
    $notOptedIn->notify(($this->alert)());

    Queue::assertPushed(SendWhatsAppMessage::class, 1);
    Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job): bool => $job->userId === $optedIn->id
        && $job->message->bodyParameters === ['طلب نقل جديد'] && ! str_contains(serialize($job), '551234567'));
    expect(DatabaseNotification::query()->where('notifiable_id', $optedIn->id)->count())->toBe(1);

    config(['services.whatsapp.enabled' => false]);
    $optedIn->notify(($this->alert)());
    Queue::assertPushed(SendWhatsAppMessage::class, 1);
});

it('sends the approved template in the user\'s language and logs it without the number or text', function (): void {
    Http::fake(['graph.test/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]], 200)]);
    $user = ($this->optedIn)('en');

    (new SendWhatsAppMessage($user->id, ($this->alert)()->toWhatsApp($user), $this->dammam->id))->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.test/v21.0/1234567890/messages'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request['to'] === '966551234567'
        && $request['template'] === (new WhatsAppTemplateMessage('office_alert', 'en', ['New transfer request']))->toPayload());

    $log = IntegrationLog::query()->sole();
    expect($log)->integration_name->toBe('whatsapp')->status->toBe('sent')->request_reference->toBe('wamid.ABC')->tenant_id->toBe($this->dammam->id)
        ->and(json_encode($log->toArray()))->not->toContain('551234567')->not->toContain('New transfer request');
});

it('drops a rejected message after logging it, retries server errors, and skips users who opted out since', function (): void {
    $user = ($this->optedIn)();
    $job = fn (): SendWhatsAppMessage => new SendWhatsAppMessage($user->id, ($this->alert)()->toWhatsApp($user));

    Http::fakeSequence('graph.test/*')
        ->push(['error' => ['message' => '(#131026) Message undeliverable']], 400)
        ->push(['error' => ['message' => 'Service unavailable']], 503);

    $job()->handle();
    expect(IntegrationLog::query()->sole())->status->toBe('rejected')->response_code->toBe(400)->error_message->toContain('undeliverable');

    expect(fn () => $job()->handle())->toThrow(RuntimeException::class);

    $user->forceFill(['whatsapp_opt_in_at' => null])->save();
    $job()->handle();
    Http::assertSentCount(2);
});

it('lets users set their number and opt in on their profile', function (): void {
    $user = grantRole(memberOf($this->dammam), 'dai', $this->dammam);
    inCenterPanel($this->dammam, $user);

    Livewire::test(OfficeProfile::class)
        ->fillForm(['whatsapp_opt_in' => true, 'phone' => ''])
        ->call('save')
        ->assertHasFormErrors(['phone']);

    Livewire::test(OfficeProfile::class)
        ->fillForm(['phone' => '055 123 4567', 'whatsapp_opt_in' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->phone_encrypted)->toBe('+966551234567')
        ->and($user->receivesWhatsApp())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'account.whatsapp_opt_in_changed')->sole()->new_values_redacted)->toBe(['opted_in' => true]);

    Livewire::test(OfficeProfile::class)->fillForm(['whatsapp_opt_in' => false])->call('save')->assertHasNoFormErrors();
    expect($user->refresh()->receivesWhatsApp())->toBeFalse();

    Livewire::test(OfficeProfile::class)->fillForm(['phone' => '12'])->call('save')->assertHasErrors(['data.phone']);
});
