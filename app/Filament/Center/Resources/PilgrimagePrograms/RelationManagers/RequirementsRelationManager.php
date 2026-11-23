<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms\RelationManagers;

use App\Domain\Pilgrimage\Actions\ManagePrograms;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
        $program = $this->getOwnerRecord();

        return $table
            ->columns([
                TextColumn::make('code')->label(__('pilgrimage.fields.code')),
                TextColumn::make('description')->label(__('pilgrimage.fields.description')),
                IconColumn::make('required')->label(__('pilgrimage.fields.required'))->boolean(),
            ])
            ->headerActions([
                Action::make('addRequirement')->label(__('pilgrimage.actions.add_requirement'))
                    ->visible(fn (): bool => auth()->user()?->hasPermission('pilgrimage.programs.manage') ?? false)
                    ->schema([
                        TextInput::make('code')->label(__('pilgrimage.fields.code'))->required()->maxLength(40)->alphaDash(),
                        TextInput::make('description')->label(__('pilgrimage.fields.description'))->required()->maxLength(255),
                        Toggle::make('required')->label(__('pilgrimage.fields.required'))->default(true),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ManagePrograms::class)->addRequirement(
                        $program instanceof PilgrimageProgram ? $program : abort(404), (string) $data['code'], (string) $data['description'], (bool) ($data['required'] ?? true), $user,
                    ), 'pilgrimage.notifications.saved')),
            ]);
    }
}
