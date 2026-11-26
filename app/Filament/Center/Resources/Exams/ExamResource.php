<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Exams;

use App\Domain\Examinations\Actions\ManageExams;
use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Enums\ExamType;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Policies\ExamPolicy;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Guidance\Services\TeacherAssignments;
use App\Filament\Center\Resources\Exams\Pages\EditExam;
use App\Filament\Center\Resources\Exams\Pages\ListExams;
use App\Filament\Center\Resources\Exams\Pages\ViewExam;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('exams.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('exams.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('guidance.navigation_group');
    }

    /** Teachers (graders without exams.view) see exams of their classes only. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return app(ExamPolicy::class)->onlyOwnClasses($user)
            ? $query->whereIn('class_id', app(TeacherAssignments::class)->classIds($user))
            : $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('exams.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextInput::make('title')->label(__('exams.fields.title'))->required()->maxLength(255),
                    Select::make('exam_type')->label(__('exams.fields.exam_type'))->required()->options(collect(ExamType::cases())->mapWithKeys(fn (ExamType $case): array => [$case->value => $case->label()])->all()),
                    Select::make('program_id')->label(__('exams.fields.program'))->required()->live()
                        ->options(fn (): array => LocalizedName::options(GuidanceProgram::query()->where('active', true)->orderBy('code'), sortByName: false)),
                    Select::make('class_id')->label(__('exams.fields.class'))
                        ->options(fn (Get $get): array => GuidanceClass::query()->where('program_id', $get('program_id'))->orderBy('code')->pluck('name', 'id')->all()),
                    DateTimePicker::make('scheduled_at')->label(__('exams.fields.scheduled_at'))->timezone((string) config('app.display_timezone'))->seconds(false),
                ]),
                Fieldset::make(__('forms.settings'))->columns(2)->schema([
                    TextInput::make('maximum_score')->label(__('exams.fields.maximum_score'))->numeric()->minValue(1)->maxValue(1000)->default(100)->required(),
                    TextInput::make('passing_score')->label(__('exams.fields.passing_score'))->numeric()->minValue(0)->default(60)->required(),
                    Repeater::make('component_rows')->label(__('exams.fields.components'))->columnSpanFull()->columns(4)->minItems(1)
                        ->schema([
                            TextInput::make('component_code')->label(__('exams.fields.component_code'))->required()->maxLength(30),
                            TextInput::make('name')->label(__('exams.fields.name'))->required()->maxLength(255),
                            TextInput::make('max_score')->label(__('exams.fields.max_score'))->numeric()->minValue(1)->required(),
                            TextInput::make('weight')->label(__('exams.fields.weight'))->numeric()->minValue(0.01)->default(1)->required(),
                        ]),
                ]),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('exams.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(3)->schema([
                    TextEntry::make('title')->label(__('exams.fields.title')),
                    TextEntry::make('exam_type')->label(__('exams.fields.exam_type'))->formatStateUsing(fn (ExamType $state): string => $state->label()),
                    TextEntry::make('status')->label(__('exams.fields.status'))->badge()->formatStateUsing(fn (ExamStatus $state): string => $state->label()),
                    TextEntry::make('scheduled_at')->label(__('exams.fields.scheduled_at'))->dateTime('Y-m-d H:i', timezone: (string) config('app.display_timezone'))->placeholder('—'),
                ]),
                Fieldset::make(__('forms.settings'))->columns(2)->schema([
                    TextEntry::make('maximum_score')->label(__('exams.fields.maximum_score')),
                    TextEntry::make('passing_score')->label(__('exams.fields.passing_score')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('exams.fields.title'))->searchable(),
                TextColumn::make('exam_type')->label(__('exams.fields.exam_type'))->formatStateUsing(fn (ExamType $state): string => $state->label()),
                TextColumn::make('status')->label(__('exams.fields.status'))->badge()->formatStateUsing(fn (ExamStatus $state): string => $state->label()),
                TextColumn::make('scheduled_at')->label(__('exams.fields.scheduled_at'))->dateTime('Y-m-d H:i', timezone: (string) config('app.display_timezone'))->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('exams.fields.status'))->options(collect(ExamStatus::cases())->mapWithKeys(fn (ExamStatus $case): array => [$case->value => $case->label()])->all()),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?Exam $exam, array $data): Exam
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $components = array_values(array_map(fn (array $row): array => [
            'component_code' => (string) $row['component_code'],
            'name' => (string) $row['name'],
            'max_score' => (string) $row['max_score'],
            'weight' => (string) $row['weight'],
        ], (array) ($data['component_rows'] ?? [])));

        try {
            return app(ManageExams::class)->save($exam, $data, $components, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getRelations(): array
    {
        return [RelationManagers\AttemptsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExams::route('/'),
            'view' => ViewExam::route('/{record}'),
            'edit' => EditExam::route('/{record}/edit'),
        ];
    }
}
