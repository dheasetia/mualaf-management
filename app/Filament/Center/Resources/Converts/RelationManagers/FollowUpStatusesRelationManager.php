<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\FollowUp\Actions\RecordFollowUpStatus;
use App\Domain\FollowUp\Enums\ActivityLevel;
use App\Domain\FollowUp\Enums\CommunityConnection;
use App\Domain\FollowUp\Enums\FollowUpState;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\Platform\Models\Country;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use App\Support\Tenancy\TenantScope;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Long-term follow-up status history (10 §10.3), across offices: the
 * history follows the convert after a transfer.
 */
final class FollowUpStatusesRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'followUpStatuses';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('followup.statuses.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Convert && (auth()->user()?->can('viewFollowUps', $ownerRecord) ?? false);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $convert = $this->getOwnerRecord();
        $timezone = (string) config('app.display_timezone');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withoutGlobalScope(TenantScope::class))
            ->columns([
                TextColumn::make('created_at')->label(__('followup.fields.recorded_at'))->dateTime('Y-m-d H:i', $timezone),
                TextColumn::make('status')->label(__('followup.fields.status'))->badge()
                    ->formatStateUsing(fn (FollowUpState $state): string => $state->label())->color(fn (FollowUpState $state): string => $state->color()),
                TextColumn::make('follow_up_priority')->label(__('followup.fields.priority'))->formatStateUsing(fn (Priority $state): string => $state->label()),
                TextColumn::make('last_contact_at')->label(__('followup.fields.last_contact_at'))->date('Y-m-d', $timezone)->placeholder('—'),
                TextColumn::make('notes')->label(__('followup.fields.notes'))->limit(50)->placeholder('—'),
            ])
            ->headerActions([
                Action::make('recordStatus')->label(__('followup.actions.record_status'))
                    ->visible(fn (): bool => $convert instanceof Convert && (auth()->user()?->can('recordFollowUp', $convert) ?? false))
                    ->schema([
                        Select::make('status')->label(__('followup.fields.status'))->required()->options(collect(FollowUpState::cases())->mapWithKeys(fn (FollowUpState $case): array => [$case->value => $case->label()])->all()),
                        Select::make('follow_up_priority')->label(__('followup.fields.priority'))->required()->default(Priority::Normal->value)->options(collect(Priority::cases())->mapWithKeys(fn (Priority $case): array => [$case->value => $case->label()])->all()),
                        DateTimePicker::make('last_contact_at')->label(__('followup.fields.last_contact_at'))->seconds(false),
                        TextInput::make('last_contact_method')->label(__('followup.fields.last_contact_method'))->maxLength(30),
                        Select::make('last_known_country_id')->label(__('followup.fields.last_known_country'))->searchable()
                            ->options(fn (): array => LocalizedName::options(Country::query())),
                        TextInput::make('last_known_city')->label(__('followup.fields.last_known_city'))->maxLength(255),
                        Select::make('islamic_activity_status')->label(__('followup.fields.islamic_activity_status'))->options(collect(ActivityLevel::cases())->mapWithKeys(fn (ActivityLevel $case): array => [$case->value => $case->label()])->all()),
                        Select::make('community_connection_status')->label(__('followup.fields.community_connection_status'))->options(collect(CommunityConnection::cases())->mapWithKeys(fn (CommunityConnection $case): array => [$case->value => $case->label()])->all()),
                        DateTimePicker::make('next_follow_up_at')->label(__('followup.fields.next_follow_up_at'))->seconds(false),
                        Textarea::make('notes')->label(__('followup.fields.notes'))->maxLength(2000),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(RecordFollowUpStatus::class)
                        ->handle($convert instanceof Convert ? $convert : abort(404), $data, $user), 'followup.notifications.saved')),
            ]);
    }
}
