<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Runs a domain action from a Filament action: rule violations become a
 * danger notification and halt the action; success shows a notification.
 */
trait RunsDomainActions
{
    /**
     * @param  Closure(mixed, array<string, mixed>, User): mixed  $handler  receives the record (or null), form data and actor
     */
    protected static function domainAction(Closure $handler, string $successMessage = 'guidance.notifications.saved'): Closure
    {
        return function (Action $action, array $data = [], mixed $record = null) use ($handler, $successMessage): void {
            $user = auth()->user();

            if (! $user instanceof User) {
                abort(403);
            }

            try {
                $handler($record, $data, $user);
            } catch (RuleViolation $violation) {
                Notification::make()->danger()->title($violation->getMessage())->send();
                $action->halt();
            }

            Notification::make()->success()->title(__($successMessage))->send();
        };
    }
}
