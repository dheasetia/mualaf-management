<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Channels\WhatsAppChannel;
use App\Domain\Notifications\Data\WhatsAppTemplateMessage;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An in-panel notification for office staff, or for platform staff in
 * Central when tenantId is null (11 §11.3). Text is generic,
 * from notifications.{kind}.title/body in the recipient's language, with a
 * link; never identity numbers, previous religion or shahadah details.
 * Users who opted in also get the title on WhatsApp (open question #9).
 */
final class OfficeAlert extends Notification
{
    use Queueable;

    /** @param  array<string, string|int>  $parameters */
    public function __construct(
        public readonly string $kind,
        public readonly ?int $tenantId,
        public readonly array $parameters = [],
        public readonly ?string $url = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $useWhatsApp = WhatsAppChannel::isEnabled() && $notifiable instanceof User && $notifiable->receivesWhatsApp();

        return $useWhatsApp ? ['database', WhatsAppChannel::class] : ['database'];
    }

    /** The approved template with the alert title (generic text) in the user's language. */
    public function toWhatsApp(User $notifiable): WhatsAppTemplateMessage
    {
        $locale = $this->localeFor($notifiable);
        /** @var array<string, string> $languages */
        $languages = config('services.whatsapp.languages');

        return new WhatsAppTemplateMessage(
            (string) config('services.whatsapp.template'),
            $languages[$locale] ?? $locale,
            [(string) __('notifications.'.$this->kind.'.title', [], $locale)],
        );
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $locale = $this->localeFor($notifiable);

        return [
            ...FilamentNotification::make()
                ->title(__('notifications.'.$this->kind.'.title', $this->parameters, $locale))
                ->body(__('notifications.'.$this->kind.'.body', $this->parameters, $locale))
                ->icon('heroicon-o-bell')
                ->actions($this->url === null ? [] : [Action::make('open')->label(__('notifications.open', [], $locale))->url($this->url)->markAsRead()])
                ->getDatabaseMessage(),
            'kind' => $this->kind,
            'tenant_id' => $this->tenantId,
        ];
    }

    private function localeFor(object $notifiable): string
    {
        return $notifiable instanceof User ? (string) ($notifiable->getAttribute('locale') ?? app()->getLocale()) : app()->getLocale();
    }
}
