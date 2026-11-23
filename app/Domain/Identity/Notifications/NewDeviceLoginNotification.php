<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewDeviceLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $ipAddress, public readonly string $browser, public readonly CarbonImmutable $at) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.new_device.subject'))
            ->line(__('identity.new_device.line', ['time' => $this->at->setTimezone((string) config('app.display_timezone'))->format('Y-m-d H:i')]))
            ->line(__('identity.new_device.details', ['ip' => $this->ipAddress ?? '-', 'browser' => $this->browser]))
            ->line(__('identity.locked.advice'));
    }
}
