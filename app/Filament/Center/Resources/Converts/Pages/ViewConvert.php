<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\Pages;

use App\Domain\Converts\Actions\AddConvertContact;
use App\Domain\Converts\Actions\AssignConvertDai;
use App\Domain\Converts\Actions\ChangeConvertStatus;
use App\Domain\Converts\Actions\ChangeDisclosureLevel;
use App\Domain\Converts\Actions\RecordConvertFollowUp;
use App\Domain\Converts\Actions\RemoveConvertContact;
use App\Domain\Converts\Enums\ContactType;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContact;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Dai\Data\FollowUpData;
use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\FollowUpType;
use App\Domain\Dai\Services\DaiDirectory;
use App\Filament\Center\Resources\Converts\ConvertResource;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property Convert $record
 */
final class ViewConvert extends ViewRecord
{
    use ResolvesActor;

    protected static string $resource = ConvertResource::class;

    protected function getHeaderActions(): array
    {
        $can = fn (string $ability): Closure => fn (): bool => auth()->user()?->can($ability, $this->record) ?? false;

        return [
            EditAction::make(),
            Action::make('recordFollowUp')->label(__('prospects.actions.record_follow_up'))->icon('heroicon-o-chat-bubble-left-right')
                ->authorize($can('recordFollowUp'))
                ->schema([
                    Select::make('follow_up_type')->label(__('prospects.fields.follow_up_type'))->required()
                        ->options(collect(FollowUpType::cases())->mapWithKeys(fn (FollowUpType $type): array => [$type->value => $type->label()])->all()),
                    Select::make('contact_method')->label(__('prospects.fields.contact_method'))->required()
                        ->options(collect(ContactMethod::cases())->mapWithKeys(fn (ContactMethod $method): array => [$method->value => $method->label()])->all()),
                    DateTimePicker::make('occurred_at')->label(__('prospects.fields.occurred_at'))->required()
                        ->timezone((string) config('app.display_timezone'))->default(now())->maxDate(now()->addMinutes(5)),
                    Textarea::make('summary')->label(__('prospects.fields.summary'))->required()->maxLength(5000),
                    TextInput::make('next_action')->label(__('prospects.fields.next_action'))->maxLength(255),
                    DateTimePicker::make('next_follow_up_at')->label(__('prospects.fields.next_follow_up_at'))
                        ->timezone((string) config('app.display_timezone'))->after('occurred_at'),
                    Select::make('confidentiality_level')->label(__('prospects.fields.confidentiality_level'))->required()
                        ->options(collect(Confidentiality::cases())->mapWithKeys(fn (Confidentiality $level): array => [$level->value => $level->label()])->all())
                        ->default(Confidentiality::Normal->value),
                ])
                ->action($this->run(fn (array $data) => app(RecordConvertFollowUp::class)
                    ->handle($this->record, FollowUpData::fromForm($data), $this->actor()))),
            ActionGroup::make([
                Action::make('assignDai')->label(__('converts.actions.assign_dai'))->authorize($can('assignDai'))
                    ->schema([
                        Select::make('dai_id')->label(__('converts.fields.primary_dai'))->required()->searchable()
                            ->options(fn (): array => app(DaiDirectory::class)->activeOptions())
                            ->default(fn (): int => $this->record->primary_dai_id),
                        Textarea::make('reason')->label(__('converts.fields.reason'))->maxLength(500),
                    ])
                    ->action($this->run(fn (array $data) => app(AssignConvertDai::class)
                        ->handle($this->record, (int) $data['dai_id'], $this->actor(), $data['reason'] ?? null))),

                Action::make('changeDisclosure')->label(__('converts.actions.change_disclosure'))->authorize($can('setDisclosure'))
                    ->schema([
                        Select::make('level')->label(__('converts.fields.disclosure_risk_level'))->required()
                            ->options(collect(DisclosureLevel::cases())->mapWithKeys(fn (DisclosureLevel $level): array => [$level->value => $level->label()])->all())
                            ->default(fn (): string => $this->record->disclosure_risk_level->value),
                        Textarea::make('reason')->label(__('converts.fields.reason'))->required()->maxLength(500),
                    ])
                    ->action($this->run(fn (array $data) => app(ChangeDisclosureLevel::class)
                        ->handle($this->record, DisclosureLevel::from((string) $data['level']), $this->actor(), $data['reason'] ?? null))),

                Action::make('changeStatus')->label(__('converts.actions.change_status'))->authorize($can('changeStatus'))
                    ->requiresConfirmation()
                    ->schema([
                        Select::make('status')->label(__('converts.fields.status'))->required()
                            ->options(fn (): array => collect($this->record->status->allowedTransitions())
                                ->mapWithKeys(fn (ConvertStatus $status): array => [$status->value => $status->label()])->all()),
                        Textarea::make('reason')->label(__('converts.fields.reason'))->required()->maxLength(500),
                    ])
                    ->action($this->run(fn (array $data) => app(ChangeConvertStatus::class)
                        ->handle($this->record, ConvertStatus::from((string) $data['status']), $this->actor(), $data['reason'] ?? null))),

                Action::make('addContact')->label(__('converts.actions.add_contact'))->authorize($can('manageContacts'))
                    ->schema([
                        Select::make('contact_type')->label(__('converts.fields.contact_type'))->required()
                            ->options(collect(ContactType::cases())->mapWithKeys(fn (ContactType $type): array => [$type->value => $type->label()])->all()),
                        TextInput::make('value')->label(__('converts.fields.value'))->required()->maxLength(255)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Toggle::make('is_primary')->label(__('converts.fields.is_primary')),
                    ])
                    ->action($this->run(fn (array $data) => app(AddConvertContact::class)
                        ->handle($this->record, ContactType::from((string) $data['contact_type']), (string) $data['value'], $this->actor(), (bool) ($data['is_primary'] ?? false)))),

                Action::make('removeContact')->label(__('converts.actions.remove_contact'))->authorize($can('manageContacts'))
                    ->color('danger')
                    ->schema([
                        Select::make('contact_id')->label(__('converts.fields.value'))->required()
                            ->options(fn (): array => $this->record->contacts()->where('active', true)->get()
                                ->mapWithKeys(fn (ConvertContact $contact): array => [$contact->id => $contact->contact_type->label().': '.$contact->value_encrypted])
                                ->all()),
                    ])
                    ->action($this->run(fn (array $data) => app(RemoveConvertContact::class)
                        ->handle($this->record->contacts()->findOrFail((int) $data['contact_id']), $this->actor()))),

                Action::make('revealIdentity')->label(__('converts.actions.reveal'))->authorize($can('revealIdentity'))
                    ->schema([
                        Select::make('identifier_id')->label(__('converts.fields.identity_number'))->required()
                            ->options(fn (): array => $this->record->identifiers()->whereNull('replaced_at')->get()
                                ->mapWithKeys(fn (ConvertIdentifier $identifier): array => [$identifier->id => $identifier->identity_type->label().' '.$identifier->masked()])
                                ->all()),
                    ])
                    ->action(function (array $data): void {
                        $identifier = $this->record->identifiers()->findOrFail((int) $data['identifier_id']);
                        $number = app(IdentityRegistry::class)->reveal($identifier, $this->actor());

                        Notification::make()->warning()->persistent()
                            ->title(__('converts.notifications.revealed', ['number' => $number]))->send();
                    }),
            ]),
        ];
    }

    /**
     * @param  Closure(array<string, mixed>): mixed  $handler
     * @return Closure(array<string, mixed>, Action): void
     */
    private function run(Closure $handler): Closure
    {
        return function (array $data, Action $action) use ($handler): void {
            try {
                $handler($data);
            } catch (RuleViolation $violation) {
                Notification::make()->danger()->title($violation->getMessage())->send();
                $action->halt();
            }

            $this->record->refresh();
            Notification::make()->success()->title(__('converts.notifications.saved'))->send();
        };
    }
}
