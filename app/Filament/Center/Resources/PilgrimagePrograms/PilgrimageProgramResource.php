<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms;

use App\Domain\Pilgrimage\Actions\ManagePrograms;
use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Enums\ProgramType;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Filament\Center\Resources\PilgrimagePrograms\Pages\CreatePilgrimageProgram;
use App\Filament\Center\Resources\PilgrimagePrograms\Pages\EditPilgrimageProgram;
use App\Filament\Center\Resources\PilgrimagePrograms\Pages\ListPilgrimagePrograms;
use App\Filament\Center\Resources\PilgrimagePrograms\Pages\ViewPilgrimageProgram;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * Umrah and hajj programs of the office (10 §10.2).
 */
final class PilgrimageProgramResource extends Resource
{
    protected static ?string $model = PilgrimageProgram::class;

    protected static ?string $slug = 'pilgrimage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('pilgrimage.programs.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pilgrimage.programs.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('pilgrimage.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('pilgrimage.programs.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    Select::make('program_type')->label(__('pilgrimage.fields.program_type'))->required()
                        ->options(collect(ProgramType::cases())->mapWithKeys(fn (ProgramType $type): array => [$type->value => $type->label()])->all()),
                    TextInput::make('name')->label(__('pilgrimage.fields.name'))->required()->maxLength(255),
                    TextInput::make('season_year')->label(__('pilgrimage.fields.season_year'))->required()->integer()->minValue(2024)->maxValue(2100)->default((int) now()->format('Y')),
                    TextInput::make('capacity')->label(__('pilgrimage.fields.capacity'))->required()->integer()->minValue(1)->maxValue(5000),
                ]),
                Fieldset::make(__('forms.schedule'))->columns(3)->schema([
                    DatePicker::make('starts_on')->label(__('pilgrimage.fields.starts_on'))->required(),
                    DatePicker::make('ends_on')->label(__('pilgrimage.fields.ends_on'))->required(),
                    DatePicker::make('registration_deadline')->label(__('pilgrimage.fields.registration_deadline'))->required(),
                ]),
                Fieldset::make(__('forms.settings'))->columns(2)->schema([
                    TextInput::make('min_months_since_shahadah')->label(__('pilgrimage.fields.min_months_since_shahadah'))->integer()->minValue(0)->maxValue(120),
                    Toggle::make('require_completed_guidance')->label(__('pilgrimage.fields.require_completed_guidance')),
                ]),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('pilgrimage.programs.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(3)->schema([
                    TextEntry::make('name')->label(__('pilgrimage.fields.name')),
                    TextEntry::make('program_type')->label(__('pilgrimage.fields.program_type'))->formatStateUsing(fn (ProgramType $state): string => $state->label()),
                    TextEntry::make('status')->label(__('pilgrimage.fields.status'))->badge()->formatStateUsing(fn (ProgramStatus $state): string => $state->label()),
                    TextEntry::make('seats')->label(__('pilgrimage.fields.seats'))
                        ->state(fn (PilgrimageProgram $record): string => app(ManagePrograms::class)->seatsTaken($record).' / '.$record->capacity),
                ]),
                Fieldset::make(__('forms.schedule'))->columns(3)->schema([
                    TextEntry::make('starts_on')->label(__('pilgrimage.fields.starts_on'))->date('Y-m-d'),
                    TextEntry::make('ends_on')->label(__('pilgrimage.fields.ends_on'))->date('Y-m-d'),
                    TextEntry::make('registration_deadline')->label(__('pilgrimage.fields.registration_deadline'))->date('Y-m-d'),
                ]),
                Fieldset::make(__('forms.settings'))->columns(2)->schema([
                    TextEntry::make('eligibility_rules.min_months_since_shahadah')->label(__('pilgrimage.fields.min_months_since_shahadah'))->placeholder('—'),
                    IconEntry::make('eligibility_rules.require_completed_guidance')->label(__('pilgrimage.fields.require_completed_guidance'))->boolean(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('pilgrimage.fields.name'))->searchable(),
                TextColumn::make('program_type')->label(__('pilgrimage.fields.program_type'))->formatStateUsing(fn (ProgramType $state): string => $state->label()),
                TextColumn::make('starts_on')->label(__('pilgrimage.fields.starts_on'))->date('Y-m-d')->sortable(),
                TextColumn::make('registration_deadline')->label(__('pilgrimage.fields.registration_deadline'))->date('Y-m-d'),
                TextColumn::make('capacity')->label(__('pilgrimage.fields.capacity')),
                TextColumn::make('status')->label(__('pilgrimage.fields.status'))->badge()->formatStateUsing(fn (ProgramStatus $state): string => $state->label()),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?PilgrimageProgram $program, array $data): PilgrimageProgram
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(ManagePrograms::class)->save($program, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RegistrationsRelationManager::class,
            RelationManagers\RequirementsRelationManager::class,
            RelationManagers\GroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPilgrimagePrograms::route('/'),
            'create' => CreatePilgrimageProgram::route('/create'),
            'view' => ViewPilgrimageProgram::route('/{record}'),
            'edit' => EditPilgrimageProgram::route('/{record}/edit'),
        ];
    }
}
