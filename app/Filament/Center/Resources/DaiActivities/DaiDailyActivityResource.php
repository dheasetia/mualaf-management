<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DaiActivities;

use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Dawah\Actions\RecordDailyActivity;
use App\Domain\Dawah\Enums\ActivityStatus;
use App\Domain\Dawah\Models\DaiDailyActivity;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Domain\Platform\Models\DaiActivityType;
use App\Filament\Center\Resources\DaiActivities\Pages\ManageDaiDailyActivities;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Daily activities: a da'i sees their own; Head of Dawah sees all to verify.
 */
final class DaiDailyActivityResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = DaiDailyActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $slug = 'dai-activities';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('dawah.activities.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dawah.activities.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dawah.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('activityType');
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $user->hasPermission('dawah.activities.verify') ? $query : $query->where('dai_id', app(DawahActors::class)->daiId($user) ?? 0);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('activity_date')->label(__('dawah.fields.activity_date'))->required()->default(now()->toDateString()),
            Select::make('activity_type_id')->label(__('dawah.fields.activity_type'))->required()->searchable()
                ->options(fn (): array => DaiActivityType::query()->where('active', true)->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
            TextInput::make('title')->label(__('dawah.fields.title'))->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->label(__('dawah.fields.description'))->maxLength(5000)->columnSpanFull(),
            TimePicker::make('started_at')->label(__('dawah.fields.started_at'))->seconds(false),
            TimePicker::make('ended_at')->label(__('dawah.fields.ended_at'))->seconds(false),
            TextInput::make('duration_minutes')->label(__('dawah.fields.duration_minutes'))->integer()->minValue(1)->maxValue(1440),
            Select::make('related_activity_plan_id')->label(__('dawah.fields.related_plan'))->searchable()
                ->options(fn (): array => DawahActivityPlan::query()->latest('planned_date')->limit(200)->pluck('title', 'id')->all()),
            TextInput::make('output_quantity')->label(__('dawah.fields.output_quantity'))->numeric()->minValue(0),
            TextInput::make('output_unit')->label(__('dawah.fields.output_unit'))->maxLength(30),
        ]);
    }

    public static function table(Table $table): Table
    {
        $activities = fn (): RecordDailyActivity => app(RecordDailyActivity::class);
        $user = fn (): ?User => ($user = auth()->user()) instanceof User ? $user : null;

        return $table
            ->defaultSort('activity_date', 'desc')
            ->columns([
                TextColumn::make('activity_date')->label(__('dawah.fields.activity_date'))->date('Y-m-d')->sortable(),
                TextColumn::make('dai')->label(__('dawah.fields.dai'))
                    ->state(fn (DaiDailyActivity $record): string => app(DaiDirectory::class)->names([$record->dai_id])[$record->dai_id] ?? '—'),
                TextColumn::make('activityType.name_ar')->label(__('dawah.fields.activity_type')),
                TextColumn::make('title')->label(__('dawah.fields.title'))->limit(40)->searchable(),
                TextColumn::make('duration_minutes')->label(__('dawah.fields.duration_minutes'))->placeholder('—'),
                TextColumn::make('status')->label(__('dawah.fields.status'))->badge()
                    ->formatStateUsing(fn (ActivityStatus $state): string => $state->label())->color(fn (ActivityStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('dawah.fields.status'))
                    ->options(collect(ActivityStatus::cases())->mapWithKeys(fn (ActivityStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Model $record, array $data): Model => $record instanceof DaiDailyActivity ? self::save($record, $data) : $record),
                Action::make('submit')->label(__('dawah.actions.submit'))->requiresConfirmation()
                    ->visible(fn (DaiDailyActivity $record): bool => $user()?->can('update', $record) ?? false)
                    ->action(self::domainAction(fn (DaiDailyActivity $record, array $data, User $actor) => $activities()->submit($record, $actor), 'dawah.notifications.saved')),
                Action::make('verify')->label(__('dawah.actions.verify'))->color('success')->requiresConfirmation()
                    ->visible(fn (DaiDailyActivity $record): bool => $record->status === ActivityStatus::Submitted && ($user()?->hasPermission('dawah.activities.verify') ?? false))
                    ->action(self::domainAction(fn (DaiDailyActivity $record, array $data, User $actor) => $activities()->verify($record, $actor), 'dawah.notifications.saved')),
                Action::make('reject')->label(__('dawah.actions.reject'))->color('danger')
                    ->visible(fn (DaiDailyActivity $record): bool => $record->status === ActivityStatus::Submitted && ($user()?->hasPermission('dawah.activities.verify') ?? false))
                    ->schema([Textarea::make('note')->label(__('dawah.fields.note'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (DaiDailyActivity $record, array $data, User $actor) => $activities()->reject($record, $actor, $data['note'] ?? null), 'dawah.notifications.saved')),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?DaiDailyActivity $activity, array $data): DaiDailyActivity
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(RecordDailyActivity::class)->save($activity, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDaiDailyActivities::route('/'),
        ];
    }
}
