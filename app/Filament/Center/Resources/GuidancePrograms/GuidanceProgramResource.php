<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidancePrograms;

use App\Domain\Guidance\Actions\ManagePrograms;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Filament\Center\Resources\GuidancePrograms\Pages\ListGuidancePrograms;
use App\Filament\Center\Resources\GuidancePrograms\Pages\ViewGuidanceProgram;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * National templates (read-only, copied into the office) and the office's own programs.
 */
final class GuidanceProgramResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = GuidanceProgram::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'name_ar';

    protected static bool $isScopedToTenant = false;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('guidance.programs.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('guidance.programs.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('guidance.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label(__('guidance.fields.code'))->required()->maxLength(30),
            TextInput::make('name_ar')->label(__('guidance.fields.name_ar'))->required()->maxLength(255),
            TextInput::make('name_en')->label(__('guidance.fields.name_en'))->maxLength(255),
            TextInput::make('duration_days')->label(__('guidance.fields.duration_days'))->integer()->minValue(1)->maxValue(730)->default(90)->required(),
            TextInput::make('minimum_attendance_percentage')->label(__('guidance.fields.minimum_attendance_percentage'))->numeric()->minValue(0)->maxValue(100)->default(75)->required(),
            TextInput::make('passing_score')->label(__('guidance.fields.passing_score'))->numeric()->minValue(0)->maxValue(100)->default(60)->required(),
            Toggle::make('active')->label(__('guidance.fields.active'))->default(true),
            Textarea::make('description')->label(__('guidance.fields.description'))->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('code')->label(__('guidance.fields.code')),
            TextEntry::make('name_ar')->label(__('guidance.fields.name_ar')),
            TextEntry::make('owner')->label(__('guidance.fields.owner'))->badge()
                ->state(fn (GuidanceProgram $record): string => $record->isNational() ? __('guidance.owner.national') : __('guidance.owner.office')),
            TextEntry::make('duration_days')->label(__('guidance.fields.duration_days')),
            TextEntry::make('minimum_attendance_percentage')->label(__('guidance.fields.minimum_attendance_percentage')),
            TextEntry::make('passing_score')->label(__('guidance.fields.passing_score')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label(__('guidance.fields.code'))->searchable(),
                TextColumn::make('name_ar')->label(__('guidance.fields.name_ar'))->searchable(),
                TextColumn::make('owner')->label(__('guidance.fields.owner'))->badge()
                    ->state(fn (GuidanceProgram $record): string => $record->isNational() ? __('guidance.owner.national') : __('guidance.owner.office')),
                TextColumn::make('duration_days')->label(__('guidance.fields.duration_days')),
                IconColumn::make('active')->label(__('guidance.fields.active'))->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->using(fn (Model $record, array $data): Model => $record instanceof GuidanceProgram ? self::save($record, $data) : $record),
                Action::make('copy')->label(__('guidance.actions.copy'))->icon('heroicon-o-document-duplicate')
                    ->visible(fn (GuidanceProgram $record): bool => $record->isNational() && (auth()->user()?->hasPermission('guidance.programs.manage') ?? false))
                    ->schema([TextInput::make('code')->label(__('guidance.fields.code'))->required()->maxLength(30)])
                    ->action(self::domainAction(fn (GuidanceProgram $record, array $data, User $user) => app(ManagePrograms::class)->copyNational($record, (string) $data['code'], $user))),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?GuidanceProgram $program, array $data): GuidanceProgram
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return app(ManagePrograms::class)->save($program, $data, $user);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ModulesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuidancePrograms::route('/'),
            'view' => ViewGuidanceProgram::route('/{record}'),
        ];
    }
}
