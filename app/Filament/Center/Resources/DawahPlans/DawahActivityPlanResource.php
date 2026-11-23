<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahPlans;

use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Dawah\Actions\ManagePlans;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\DaiActivityType;
use App\Filament\Center\Resources\DawahPlans\Pages\CreateDawahActivityPlan;
use App\Filament\Center\Resources\DawahPlans\Pages\EditDawahActivityPlan;
use App\Filament\Center\Resources\DawahPlans\Pages\ListDawahActivityPlans;
use App\Filament\Center\Resources\DawahPlans\Pages\ViewDawahActivityPlan;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * Dawah activity plans of the office (08 §8.2).
 */
final class DawahActivityPlanResource extends Resource
{
    protected static ?string $model = DawahActivityPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'dawah-plans';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('dawah.plans.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dawah.plans.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dawah.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label(__('dawah.fields.title'))->required()->maxLength(255)->columnSpanFull(),
            Select::make('activity_type_id')->label(__('dawah.fields.activity_type'))->required()->searchable()
                ->options(fn (): array => DaiActivityType::query()->where('active', true)->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
            Select::make('responsible_dai_id')->label(__('dawah.fields.responsible_dai'))->searchable()
                ->options(fn (): array => app(DaiDirectory::class)->activeOptions()),
            Textarea::make('objective')->label(__('dawah.fields.objective'))->required()->maxLength(5000)->columnSpanFull(),
            Textarea::make('description')->label(__('dawah.fields.description'))->maxLength(10000)->columnSpanFull(),
            TextInput::make('target_audience')->label(__('dawah.fields.target_audience'))->maxLength(255),
            TextInput::make('expected_participants')->label(__('dawah.fields.expected_participants'))->integer()->minValue(1)->maxValue(100000),
            DatePicker::make('planned_date')->label(__('dawah.fields.planned_date'))->required(),
            Select::make('city_id')->label(__('dawah.fields.city'))->searchable()
                ->options(fn (): array => City::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
            TimePicker::make('start_time')->label(__('dawah.fields.start_time'))->seconds(false),
            TimePicker::make('end_time')->label(__('dawah.fields.end_time'))->seconds(false),
            TextInput::make('location')->label(__('dawah.fields.location'))->maxLength(255),
            TextInput::make('google_maps_url')->label(__('dawah.fields.google_maps_url'))->url()->maxLength(500),
            Toggle::make('transport_required')->label(__('dawah.fields.transport_required')),
            Toggle::make('material_required')->label(__('dawah.fields.material_required')),
            Textarea::make('equipment_required')->label(__('dawah.fields.equipment_required'))->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('plan_number')->label(__('dawah.fields.plan_number')),
                TextEntry::make('status')->label(__('dawah.fields.status'))->badge()
                    ->formatStateUsing(fn (PlanStatus $state): string => $state->label())->color(fn (PlanStatus $state): string => $state->color()),
                TextEntry::make('activityType.name_ar')->label(__('dawah.fields.activity_type')),
                TextEntry::make('planned_date')->label(__('dawah.fields.planned_date'))->date('Y-m-d'),
                TextEntry::make('responsible')->label(__('dawah.fields.responsible_dai'))
                    ->state(fn (DawahActivityPlan $record): string => app(DaiDirectory::class)->names([$record->responsible_dai_id])[$record->responsible_dai_id] ?? '—'),
                TextEntry::make('location')->label(__('dawah.fields.location'))->placeholder('—'),
                TextEntry::make('objective')->label(__('dawah.fields.objective'))->columnSpanFull(),
                IconEntry::make('transport_required')->label(__('dawah.fields.transport_required'))->boolean(),
                IconEntry::make('material_required')->label(__('dawah.fields.material_required'))->boolean(),
                TextEntry::make('expected_participants')->label(__('dawah.fields.expected_participants'))->placeholder('—'),
                TextEntry::make('revision_note')->label(__('dawah.fields.revision_note'))->visible(fn (DawahActivityPlan $record): bool => $record->revision_note !== null)->columnSpanFull(),
                TextEntry::make('rejection_reason')->label(__('dawah.fields.rejection_reason'))->visible(fn (DawahActivityPlan $record): bool => $record->rejection_reason !== null)->columnSpanFull(),
                TextEntry::make('cancellation_reason')->label(__('dawah.fields.cancellation_reason'))->visible(fn (DawahActivityPlan $record): bool => $record->cancellation_reason !== null)->columnSpanFull(),
            ]),
            Section::make(__('dawah.report.title'))->columns(3)
                ->visible(fn (DawahActivityPlan $record): bool => $record->report !== null)
                ->schema([
                    TextEntry::make('report.status')->label(__('dawah.fields.report_status'))->badge()
                        ->formatStateUsing(fn ($state): string => $state->label()),
                    TextEntry::make('report.actual_participants')->label(__('dawah.fields.actual_participants')),
                    TextEntry::make('report.new_prospects_count')->label(__('dawah.fields.new_prospects_count')),
                    TextEntry::make('report.summary')->label(__('dawah.fields.summary'))->columnSpanFull(),
                    TextEntry::make('report.achievements')->label(__('dawah.fields.achievements'))->placeholder('—')->columnSpanFull(),
                    TextEntry::make('report.challenges')->label(__('dawah.fields.challenges'))->placeholder('—')->columnSpanFull(),
                    TextEntry::make('report.return_note')->label(__('dawah.fields.return_note'))->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('planned_date', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('activityType'))
            ->columns([
                TextColumn::make('plan_number')->label(__('dawah.fields.plan_number'))->searchable()->fontFamily('mono'),
                TextColumn::make('title')->label(__('dawah.fields.title'))->searchable()->limit(40),
                TextColumn::make('activityType.name_ar')->label(__('dawah.fields.activity_type')),
                TextColumn::make('planned_date')->label(__('dawah.fields.planned_date'))->date('Y-m-d')->sortable(),
                TextColumn::make('status')->label(__('dawah.fields.status'))->badge()
                    ->formatStateUsing(fn (PlanStatus $state): string => $state->label())->color(fn (PlanStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('dawah.fields.status'))
                    ->options(collect(PlanStatus::cases())->mapWithKeys(fn (PlanStatus $status): array => [$status->value => $status->label()])->all()),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?DawahActivityPlan $plan, array $data): DawahActivityPlan
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(ManagePlans::class)->save($plan, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ParticipantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDawahActivityPlans::route('/'),
            'create' => CreateDawahActivityPlan::route('/create'),
            'view' => ViewDawahActivityPlan::route('/{record}'),
            'edit' => EditDawahActivityPlan::route('/{record}/edit'),
        ];
    }
}
