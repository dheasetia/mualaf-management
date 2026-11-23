<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Data\WhatsAppTemplateMessage;
use App\Domain\Notifications\Models\IntegrationLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sends one template message through the WhatsApp Cloud API, apart from the
 * in-panel notification so a WhatsApp failure never repeats it. Rate limits,
 * server errors and lost connections are retried; a rejected request (bad
 * number, unapproved template) is logged once and dropped. Every outcome
 * goes to integration_logs without the number or the text. The number is
 * read when sending, so it never sits in the queue, and a user who opted out
 * in the meantime gets nothing.
 */
final class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly int $userId,
        public readonly WhatsAppTemplateMessage $message,
        public readonly ?int $tenantId = null,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! (bool) config('services.whatsapp.enabled') || $user === null || ! $user->receivesWhatsApp()) {
            return;
        }

        try {
            $response = Http::baseUrl((string) config('services.whatsapp.base_url'))
                ->withToken((string) config('services.whatsapp.access_token'))
                ->timeout((int) config('services.whatsapp.timeout'))
                ->acceptJson()
                ->post(sprintf('/%s/%s/messages', config('services.whatsapp.api_version'), config('services.whatsapp.phone_number_id')), [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim((string) $user->phone_encrypted, '+'),
                    'type' => 'template',
                    'template' => $this->message->toPayload(),
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('WhatsApp API unreachable.', previous: $exception);
        }

        if ($response->successful()) {
            IntegrationLog::outbound('whatsapp', 'messages:'.$this->message->template, 'sent', $this->tenantId, $response->status(), (string) $response->json('messages.0.id'));

            return;
        }

        $error = (string) ($response->json('error.message') ?? 'HTTP '.$response->status());

        if ($response->status() === 429 || $response->serverError()) {
            throw new RuntimeException("WhatsApp API {$response->status()}: {$error}");
        }

        IntegrationLog::outbound('whatsapp', 'messages:'.$this->message->template, 'rejected', $this->tenantId, $response->status(), error: $error);
    }

    public function failed(Throwable $exception): void
    {
        IntegrationLog::outbound('whatsapp', 'messages:'.$this->message->template, 'failed', $this->tenantId, error: $exception->getMessage());
    }
}
