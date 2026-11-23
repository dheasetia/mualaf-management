<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Jobs\SendWhatsAppMessage;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Queues the WhatsApp copy of an alert for a user who opted in.
 */
final class WhatsAppChannel
{
    public static function isEnabled(): bool
    {
        return (bool) config('services.whatsapp.enabled')
            && filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.access_token'));
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notifiable->receivesWhatsApp() || ! $notification instanceof OfficeAlert) {
            return;
        }

        SendWhatsAppMessage::dispatch($notifiable->id, $notification->toWhatsApp($notifiable), $notification->tenantId);
    }
}
