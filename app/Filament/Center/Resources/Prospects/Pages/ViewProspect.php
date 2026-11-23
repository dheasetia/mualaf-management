<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Prospects\Pages;

use App\Domain\Dai\Data\FollowUpData;
use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\FollowUpType;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Prospects\Actions\AssignProspectDai;
use App\Domain\Prospects\Actions\ChangeProspectStatus;
use App\Domain\Prospects\Actions\RecordProspectFollowUp;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Actions\ProspectTransfers;
use App\Filament\Center\Pages\RegisterShahadah;
use App\Filament\Center\Resources\Prospects\ProspectResource;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property Prospect $record
 */
final class ViewProspect extends ViewRecord
{
    use ResolvesActor;

    protected static string $resource = ProspectResource::class;

    protected function getHeaderActions(): array
    {
        $timezone = (string) config('app.display_timezone');

        return [
            Action::make('recordFollowUp')
                ->label(__('prospects.actions.record_follow_up'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->authorize(fn (): bool => auth()->user()?->can('recordFollowUp', $this->record) ?? false)
                ->schema([
                    Select::make('follow_up_type')->label(__('prospects.fields.follow_up_type'))->required()
                        ->options(self::options(FollowUpType::cases())),
                    Select::make('contact_method')->label(__('prospects.fields.contact_method'))->required()
                        ->options(self::options(ContactMethod::cases())),
                    DateTimePicker::make('occurred_at')->label(__('prospects.fields.occurred_at'))->required()
                        ->timezone($timezone)->default(now())->maxDate(now()->addMinutes(5)),
                    TextInput::make('duration_minutes')->label(__('prospects.fields.duration_minutes'))->integer()->minValue(1)->maxValue(1440),
                    TextInput::make('location')->label(__('prospects.fields.location'))->maxLength(255),
                    Textarea::make('summary')->label(__('prospects.fields.summary'))->required()->maxLength(5000),
                    Textarea::make('questions_raised')->label(__('prospects.fields.questions_raised'))->maxLength(5000),
                    TextInput::make('outcome')->label(__('prospects.fields.outcome'))->maxLength(255),
                    TextInput::make('next_action')->label(__('prospects.fields.next_action'))->maxLength(255),
                    DateTimePicker::make('next_follow_up_at')->label(__('prospects.fields.next_follow_up_at'))
                        ->timezone($timezone)->after('occurred_at'),
                    Select::make('confidentiality_level')->label(__('prospects.fields.confidentiality_level'))->required()
                        ->options(self::options(Confidentiality::cases()))->default(Confidentiality::Normal->value),
                ])
                ->action($this->run(fn (array $data) => app(RecordProspectFollowUp::class)
                    ->handle($this->record, FollowUpData::fromForm($data), $this->actor()), 'prospects.notifications.follow_up_recorded')),

            Action::make('changeStatus')
                ->label(__('prospects.actions.change_status'))
                ->authorize(fn (): bool => auth()->user()?->can('changeStatus', $this->record) ?? false)
                ->schema([
                    Select::make('status')->label(__('prospects.fields.status'))->required()
                        ->options(fn (): array => collect($this->record->status->manualTransitions())
                            ->mapWithKeys(fn (ProspectStatus $status): array => [$status->value => $status->label()])->all()),
                    Textarea::make('reason')->label(__('prospects.fields.reason'))->maxLength(500),
                ])
                ->action($this->run(fn (array $data) => app(ChangeProspectStatus::class)
                    ->handle($this->record, ProspectStatus::from((string) $data['status']), $this->actor(), $data['reason'] ?? null), 'prospects.notifications.status_changed')),

            Action::make('assignDai')
                ->label(__('prospects.actions.assign_dai'))
                ->authorize(fn (): bool => auth()->user()?->can('assignDai', $this->record) ?? false)
                ->schema([
                    Select::make('dai_id')->label(__('prospects.fields.primary_dai'))->required()->searchable()
                        ->options(fn (): array => app(DaiDirectory::class)->activeOptions())
                        ->default(fn (): int => $this->record->primary_dai_id),
                    Textarea::make('reason')->label(__('prospects.fields.reason'))->maxLength(500),
                ])
                ->action($this->run(fn (array $data) => app(AssignProspectDai::class)
                    ->handle($this->record, (int) $data['dai_id'], $this->actor(), $data['reason'] ?? null), 'prospects.notifications.dai_assigned')),

            Action::make('transferProspect')
                ->label(__('transfers.actions.transfer_prospect'))
                ->authorize(fn (): bool => auth()->user()?->can('transfer', $this->record) ?? false)
                ->schema([
                    Select::make('to_tenant_id')->label(__('transfers.fields.destination'))->required()->searchable()
                        ->options(fn (): array => Tenant::query()->where('status', TenantStatus::Active)
                            ->whereKeyNot($this->record->tenant_id)->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
                    Textarea::make('reason')->label(__('transfers.fields.reason'))->required()->maxLength(1000),
                ])
                ->action($this->run(fn (array $data) => app(ProspectTransfers::class)
                    ->request($this->record->id, (int) $data['to_tenant_id'], (string) $data['reason'], $this->actor()), 'transfers.notifications.saved')),

            Action::make('registerShahadah')
                ->label(__('registration.actions.register_shahadah'))
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === ProspectStatus::ReadyForShahadah && RegisterShahadah::canAccess())
                ->url(fn (): string => RegisterShahadah::getUrl(['prospect' => $this->record->id])),

            EditAction::make(),
        ];
    }

    /**
     * Runs a domain action from a modal: rule violations become a danger
     * notification, success refreshes the record.
     *
     * @param  Closure(array<string, mixed>): mixed  $handler
     * @return Closure(array<string, mixed>, Action): void
     */
    private function run(Closure $handler, string $successMessage): Closure
    {
        return function (array $data, Action $action) use ($handler, $successMessage): void {
            try {
                $handler($data);
            } catch (RuleViolation $violation) {
                Notification::make()->danger()->title($violation->getMessage())->send();
                $action->halt();
            }

            $this->record->refresh();
            Notification::make()->success()->title(__($successMessage))->send();
        };
    }

    /**
     * @param  list<FollowUpType|ContactMethod|Confidentiality>  $cases
     * @return array<string, string>
     */
    private static function options(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
