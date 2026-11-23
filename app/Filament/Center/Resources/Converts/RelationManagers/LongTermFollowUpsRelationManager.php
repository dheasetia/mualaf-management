<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\FollowUp\Actions\LongTermFollowUps;
use App\Domain\FollowUp\Enums\ContactedPerson;
use App\Domain\FollowUp\Enums\ContactMethod;
use App\Domain\FollowUp\Enums\ContactResult;
use App\Domain\FollowUp\Models\ConvertLongTermFollowUp;
use App\Domain\Platform\Models\Country;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Long-term follow-up contacts (10 §10.5), across offices; confidential
 * notes only for followups.view_confidential and their author.
 */
final class LongTermFollowUpsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'longTermFollowUps';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('followup.notes.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Convert && (auth()->user()?->can('viewFollowUps', $ownerRecord) ?? false);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    /** @return list<Component|Field> */
    public static function contactForm(): array
    {
        return [
            DateTimePicker::make('follow_up_at')->label(__('followup.fields.follow_up_at'))->seconds(false)->default(now()),
            Select::make('contact_method')->label(__('followup.fields.contact_method'))->required()->options(collect(ContactMethod::cases())->mapWithKeys(fn (ContactMethod $case): array => [$case->value => $case->label()])->all()),
            Select::make('contacted_person_type')->label(__('followup.fields.contacted_person_type'))->required()->default(ContactedPerson::Convert->value)->options(collect(ContactedPerson::cases())->mapWithKeys(fn (ContactedPerson $case): array => [$case->value => $case->label()])->all()),
            Select::make('contact_result')->label(__('followup.fields.contact_result'))->required()->options(collect(ContactResult::cases())->mapWithKeys(fn (ContactResult $case): array => [$case->value => $case->label()])->all()),
            Select::make('current_country_id')->label(__('followup.fields.current_country'))->searchable()
                ->options(fn (): array => Country::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
            TextInput::make('current_city')->label(__('followup.fields.current_city'))->maxLength(255),
            TextInput::make('mosque_or_center_name')->label(__('followup.fields.mosque_or_center_name'))->maxLength(255),
            TextInput::make('local_dai_name')->label(__('followup.fields.local_dai_name'))->maxLength(255),
            Textarea::make('support_needed')->label(__('followup.fields.support_needed'))->maxLength(2000),
            Textarea::make('next_action')->label(__('followup.fields.next_action'))->maxLength(2000),
            DateTimePicker::make('next_follow_up_at')->label(__('followup.fields.next_follow_up_at'))->seconds(false)->after('now'),
            Select::make('confidentiality_level')->label(__('followup.fields.confidentiality_level'))->required()->default(Confidentiality::Normal->value)
                ->options(collect(Confidentiality::cases())->mapWithKeys(fn (Confidentiality $case): array => [$case->value => $case->label()])->all()),
            Textarea::make('notes')->label(__('followup.fields.notes'))->maxLength(5000),
        ];
    }

    public function table(Table $table): Table
    {
        $convert = $this->getOwnerRecord();
        $timezone = (string) config('app.display_timezone');

        return $table
            ->modifyQueryUsing(fn ($query) => ($user = auth()->user()) instanceof User
                ? $query->withoutGlobalScope(TenantScope::class)->visibleTo($user)
                : $query->whereRaw('1 = 0'))
            ->columns([
                TextColumn::make('follow_up_at')->label(__('followup.fields.follow_up_at'))->dateTime('Y-m-d H:i', $timezone),
                TextColumn::make('contact_method')->label(__('followup.fields.contact_method'))->formatStateUsing(fn (ContactMethod $state): string => $state->label()),
                TextColumn::make('contact_result')->label(__('followup.fields.contact_result'))->badge()->formatStateUsing(fn (ContactResult $state): string => $state->label()),
                TextColumn::make('current_city')->label(__('followup.fields.current_city'))->placeholder('—'),
                TextColumn::make('next_action')->label(__('followup.fields.next_action'))->limit(40)->placeholder('—'),
            ])
            ->headerActions([
                Action::make('record')->label(__('followup.actions.record'))
                    ->visible(fn (): bool => $convert instanceof Convert && (auth()->user()?->can('recordFollowUp', $convert) ?? false))
                    ->schema(self::contactForm())
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(LongTermFollowUps::class)
                        ->record($convert instanceof Convert ? $convert : abort(404), $data, null, $user), 'followup.notifications.saved')),
            ])
            ->recordActions([
                Action::make('view')->label(__('filament-actions::view.single.label'))->icon('heroicon-o-eye')
                    ->schema(fn (Schema $schema): Schema => $schema)
                    ->modalContent(fn (ConvertLongTermFollowUp $record): Htmlable => new HtmlString(
                        '<dl class="grid grid-cols-2 gap-2 text-sm">'.collect(['support_needed', 'next_action', 'mosque_or_center_name', 'local_dai_name', 'notes'])
                            ->map(fn (string $field): string => '<dt class="text-gray-500">'.e(__('followup.fields.'.$field)).'</dt><dd>'.e((string) ($record->getAttribute($field) ?? '—')).'</dd>')->implode('').'</dl>'
                    ))
                    ->modalSubmitAction(false),
            ]);
    }
}
