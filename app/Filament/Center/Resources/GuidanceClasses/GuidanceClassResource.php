<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidanceClasses;

use App\Domain\Guidance\Actions\ManageClasses;
use App\Domain\Guidance\Enums\ClassStatus;
use App\Domain\Guidance\Enums\GenderPolicy;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Guidance\Services\TeacherAssignments;
use App\Domain\Platform\Models\Language;
use App\Filament\Center\Resources\GuidanceClasses\Pages\EditGuidanceClass;
use App\Filament\Center\Resources\GuidanceClasses\Pages\ListGuidanceClasses;
use App\Filament\Center\Resources\GuidanceClasses\Pages\ViewGuidanceClass;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Classes of the office; teachers see only the classes assigned to them.
 */
final class GuidanceClassResource extends Resource
{
    protected static ?string $model = GuidanceClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('guidance.classes.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('guidance.classes.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('guidance.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['program', 'language']);
        $user = auth()->user();

        return $user instanceof User ? app(TeacherAssignments::class)->visibleClasses($query, $user) : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('program_id')->label(__('guidance.fields.program'))->required()->searchable()
                ->options(fn (): array => LocalizedName::options(GuidanceProgram::query()->where('active', true)->orderBy('code'), sortByName: false)),
            TextInput::make('code')->label(__('guidance.fields.code'))->required()->maxLength(30),
            TextInput::make('name')->label(__('guidance.fields.name'))->required()->maxLength(255),
            Select::make('language_id')->label(__('guidance.fields.language'))->required()->searchable()
                ->options(fn (): array => LocalizedName::options(Language::query())),
            Select::make('gender_policy')->label(__('guidance.fields.gender_policy'))->required()->options(collect(GenderPolicy::cases())->mapWithKeys(fn (GenderPolicy $case): array => [$case->value => $case->label()])->all()),
            TextInput::make('capacity')->label(__('guidance.fields.capacity'))->integer()->minValue(1)->maxValue(500)->required(),
            TextInput::make('room')->label(__('guidance.fields.room'))->maxLength(255),
            TextInput::make('location')->label(__('guidance.fields.location'))->maxLength(255),
            DatePicker::make('starts_on')->label(__('guidance.fields.starts_on'))->required(),
            DatePicker::make('ends_on')->label(__('guidance.fields.ends_on'))->required()->afterOrEqual('starts_on'),
            Repeater::make('schedule_rows')->label(__('guidance.fields.schedules'))->columns(3)->columnSpanFull()->defaultItems(0)
                ->schema([
                    Select::make('day_of_week')->label(__('guidance.fields.day_of_week'))->required()
                        ->options(array_combine(range(0, 6), (array) __('guidance.days'))),
                    TimePicker::make('start_time')->label(__('guidance.fields.start_time'))->required()->seconds(false),
                    TimePicker::make('end_time')->label(__('guidance.fields.end_time'))->required()->seconds(false)->after('start_time'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('code')->label(__('guidance.fields.code')),
            TextEntry::make('program.name_ar')->formatStateUsing(fn (GuidanceClass $record): ?string => LocalizedName::of($record->program))->label(__('guidance.fields.program')),
            TextEntry::make('status')->label(__('guidance.fields.status'))->badge()
                ->formatStateUsing(fn (ClassStatus $state): string => $state->label()),
            TextEntry::make('language.'.LocalizedName::column())->label(__('guidance.fields.language')),
            TextEntry::make('gender_policy')->label(__('guidance.fields.gender_policy'))
                ->formatStateUsing(fn (GenderPolicy $state): string => $state->label()),
            TextEntry::make('capacity')->label(__('guidance.fields.capacity')),
            TextEntry::make('starts_on')->label(__('guidance.fields.starts_on'))->date('Y-m-d'),
            TextEntry::make('ends_on')->label(__('guidance.fields.ends_on'))->date('Y-m-d'),
            TextEntry::make('room')->label(__('guidance.fields.room'))->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('code')->label(__('guidance.fields.code'))->searchable(),
                TextColumn::make('name')->label(__('guidance.fields.name'))->searchable(),
                TextColumn::make('program.name_ar')->formatStateUsing(fn (GuidanceClass $record): ?string => LocalizedName::of($record->program))->label(__('guidance.fields.program')),
                TextColumn::make('language.'.LocalizedName::column())->label(__('guidance.fields.language')),
                TextColumn::make('status')->label(__('guidance.fields.status'))->badge()
                    ->formatStateUsing(fn (ClassStatus $state): string => $state->label()),
                TextColumn::make('enrolled')->label(__('guidance.fields.enrolled_count'))
                    ->state(fn (GuidanceClass $record): string => $record->enrollments()->whereIn('status', ['enrolled', 'active', 'paused'])->count().' / '.$record->capacity),
                TextColumn::make('starts_on')->label(__('guidance.fields.starts_on'))->date('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('guidance.fields.status'))->options(collect(ClassStatus::cases())->mapWithKeys(fn (ClassStatus $case): array => [$case->value => $case->label()])->all()),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(?GuidanceClass $class, array $data): GuidanceClass
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $schedules = array_values(array_map(fn (array $row): array => [
            'day_of_week' => (int) $row['day_of_week'],
            'start_time' => (string) $row['start_time'],
            'end_time' => (string) $row['end_time'],
        ], (array) ($data['schedule_rows'] ?? [])));

        try {
            return app(ManageClasses::class)->save($class, $data, $schedules, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TeachersRelationManager::class,
            RelationManagers\EnrollmentsRelationManager::class,
            RelationManagers\SessionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuidanceClasses::route('/'),
            'view' => ViewGuidanceClass::route('/{record}'),
            'edit' => EditGuidanceClass::route('/{record}/edit'),
        ];
    }
}
