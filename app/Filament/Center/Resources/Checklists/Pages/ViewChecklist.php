<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Checklists\Pages;

use App\Domain\International\Actions\Checklists;
use App\Domain\International\Enums\ChecklistStatus;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Filament\Center\Resources\Checklists\ChecklistResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property PreDepartureChecklist $record
 */
final class ViewChecklist extends ViewRecord
{
    use RunsDomainActions;

    protected static string $resource = ChecklistResource::class;

    protected function getHeaderActions(): array
    {
        $open = fn (): bool => $this->record->status === ChecklistStatus::Open && (auth()->user()?->hasPermission('followups.create') ?? false);

        return [
            Action::make('complete')->label(__('international.actions.complete'))->color('success')->requiresConfirmation()->visible($open)
                ->action(self::domainAction(function (mixed $record, array $data, User $user): void {
                    app(Checklists::class)->complete($this->record, $user);
                    $this->record->refresh();
                }, 'international.notifications.saved')),
            Action::make('cancel')->label(__('international.actions.cancel'))->color('danger')->requiresConfirmation()->visible($open)
                ->action(self::domainAction(function (mixed $record, array $data, User $user): void {
                    app(Checklists::class)->cancel($this->record, $user);
                    $this->record->refresh();
                }, 'international.notifications.saved')),
        ];
    }
}
