<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimageRegistrations\RelationManagers;

use App\Domain\Pilgrimage\Actions\RegistrationWorkflow;
use App\Domain\Pilgrimage\Enums\RequirementStatus;
use App\Domain\Pilgrimage\Models\PilgrimageRegistrationRequirement;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A pilgrim's documents per program requirement.
 */
final class RequirementsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'requirements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pilgrimage.requirements.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $workflow = fn (): RegistrationWorkflow => app(RegistrationWorkflow::class);
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('requirement'))
            ->columns([
                TextColumn::make('requirement.description')->label(__('pilgrimage.fields.description')),
                IconColumn::make('requirement.required')->label(__('pilgrimage.fields.required'))->boolean(),
                TextColumn::make('status')->label(__('pilgrimage.fields.status'))->badge()->formatStateUsing(fn (RequirementStatus $state): string => $state->label()),
                TextColumn::make('note')->label(__('pilgrimage.fields.note'))->placeholder('—'),
            ])
            ->recordActions([
                Action::make('attach')->label(__('pilgrimage.actions.attach'))->icon('heroicon-o-paper-clip')
                    ->visible(fn (PilgrimageRegistrationRequirement $record): bool => $record->status === RequirementStatus::Pending && $can('pilgrimage.register'))
                    ->schema([
                        FileUpload::make('file')->label(__('pilgrimage.fields.file'))->required()->storeFiles(false)
                            ->maxSize((int) (config('documents.max_bytes') / 1024))
                            ->acceptedFileTypes(array_keys((array) config('documents.allowed_mime_types'))),
                    ])
                    ->action(self::domainAction(function (PilgrimageRegistrationRequirement $record, array $data, User $user) use ($workflow): void {
                        $file = $data['file'] ?? null;
                        $file instanceof TemporaryUploadedFile ? $workflow()->attach($record, $file->getRealPath(), $file->getClientOriginalName(), $user) : abort(422);
                    }, 'pilgrimage.notifications.saved')),
                Action::make('download')->label(__('pilgrimage.actions.download'))->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (PilgrimageRegistrationRequirement $record): bool => $record->document_id !== null)
                    ->action(function (PilgrimageRegistrationRequirement $record) use ($workflow): void {
                        $user = auth()->user();
                        $this->redirect($workflow()->documentUrl($record, $user instanceof User ? $user : abort(403)));
                    }),
                Action::make('fulfil')->label(__('pilgrimage.actions.fulfil'))->color('success')
                    ->visible(fn (PilgrimageRegistrationRequirement $record): bool => $record->status === RequirementStatus::Pending && $record->document_id !== null && $can('pilgrimage.check_eligibility'))
                    ->action(self::domainAction(fn (PilgrimageRegistrationRequirement $record, array $data, User $user) => $workflow()->fulfil($record, $user), 'pilgrimage.notifications.saved')),
                Action::make('waive')->label(__('pilgrimage.actions.waive'))->color('gray')
                    ->visible(fn (PilgrimageRegistrationRequirement $record): bool => $record->status === RequirementStatus::Pending && $can('pilgrimage.check_eligibility'))
                    ->schema([Textarea::make('note')->label(__('pilgrimage.fields.note'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (PilgrimageRegistrationRequirement $record, array $data, User $user) => $workflow()->waive($record, $data['note'] ?? null, $user), 'pilgrimage.notifications.saved')),
            ]);
    }
}
