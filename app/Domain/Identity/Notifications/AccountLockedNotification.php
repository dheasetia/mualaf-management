<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Services\AccountLockout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AccountLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $ipAddress) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.locked.subject'))
            ->line(__('identity.locked.line', [
                'attempts' => AccountLockout::MAX_ATTEMPTS,
                'minutes' => intdiv(AccountLockout::DECAY_SECONDS, 60),
            ]))
            ->line(__('identity.locked.ip', ['ip' => $this->ipAddress ?? '-']))
            ->line(__('identity.locked.advice'));
    }
}
