<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms\Pages;

use App\Domain\Pilgrimage\Actions\ManagePrograms;
use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Filament\Center\Resources\PilgrimagePrograms\PilgrimageProgramResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property PilgrimageProgram $record
 */
final class ViewPilgrimageProgram extends ViewRecord
{
    use RunsDomainActions;

    protected static string $resource = PilgrimageProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => auth()->user()?->can('update', $this->record) ?? false),
            Action::make('changeStatus')->label(__('pilgrimage.actions.change_status'))
                ->visible(fn (): bool => (auth()->user()?->hasPermission('pilgrimage.programs.manage') ?? false) && $this->record->status->allowedTransitions() !== [])
                ->schema([
                    Select::make('status')->label(__('pilgrimage.fields.status'))->required()
                        ->options(fn (): array => collect($this->record->status->allowedTransitions())->mapWithKeys(fn (ProgramStatus $status): array => [$status->value => $status->label()])->all()),
                ])
                ->action(self::domainAction(function (mixed $record, array $data, User $user): void {
                    app(ManagePrograms::class)->changeStatus($this->record, ProgramStatus::from((string) $data['status']), $user);
                    $this->record->refresh();
                }, 'pilgrimage.notifications.saved')),
        ];
    }
}
