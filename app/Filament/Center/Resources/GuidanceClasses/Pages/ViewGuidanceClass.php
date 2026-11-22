<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidanceClasses\Pages;

use App\Domain\Guidance\Actions\ManageClasses;
use App\Domain\Guidance\Enums\ClassStatus;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Filament\Center\Resources\GuidanceClasses\GuidanceClassResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property GuidanceClass $record
 */
final class ViewGuidanceClass extends ViewRecord
{
    use RunsDomainActions;

    protected static string $resource = GuidanceClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')->label(__('guidance.actions.change_status'))
                ->visible(fn (): bool => (auth()->user()?->hasPermission('guidance.classes.manage') ?? false) && $this->record->status->allowedTransitions() !== [])
                ->schema([
                    Select::make('status')->label(__('guidance.fields.status'))->required()
                        ->options(fn (): array => collect($this->record->status->allowedTransitions())->mapWithKeys(fn (ClassStatus $status): array => [$status->value => $status->label()])->all()),
                ])
                ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ManageClasses::class)
                    ->changeStatus($this->record, ClassStatus::from((string) $data['status']), $user))),
            EditAction::make()->visible(fn (): bool => auth()->user()?->hasPermission('guidance.classes.manage') ?? false),
        ];
    }
}
