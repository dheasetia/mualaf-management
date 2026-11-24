<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms\Pages;

use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Pilgrimage\Actions\ManagePrograms;
use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Transport\Actions\PilgrimPickups;
use App\Domain\Transport\Enums\ActivityTransportStatus;
use App\Domain\Transport\Models\ActivityTransportRequest;
use App\Filament\Center\Resources\PilgrimagePrograms\PilgrimageProgramResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;

/**
 * @property PilgrimageProgram $record
 */
final class ViewPilgrimageProgram extends ViewRecord
{
    use RunsDomainActions;

    protected static string $resource = PilgrimageProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => auth()->user()?->can('update', $this->record) ?? false),
            Action::make('changeStatus')->label(__('pilgrimage.actions.change_status'))
                ->visible(fn (): bool => (auth()->user()?->hasPermission('pilgrimage.programs.manage') ?? false) && $this->record->status->allowedTransitions() !== [])
                ->schema([
                    Select::make('status')->label(__('pilgrimage.fields.status'))->required()
                        ->options(fn (): array => collect($this->record->status->allowedTransitions())->mapWithKeys(fn (ProgramStatus $status): array => [$status->value => $status->label()])->all()),
                ])
                ->action(self::domainAction(function (mixed $record, array $data, User $user): void {
                    app(ManagePrograms::class)->changeStatus($this->record, ProgramStatus::from((string) $data['status']), $user);
                    $this->record->refresh();
                }, 'pilgrimage.notifications.saved')),
            Action::make('requestPickups')->label(__('pilgrimage.actions.request_pickups'))->icon('heroicon-o-truck')->color('gray')
                ->visible(fn (): bool => auth()->user()?->hasPermission('transport.request') ?? false)
                ->modalDescription(__('pilgrimage.help.request_pickups'))
                ->schema([
                    Select::make('activity_plan_id')->label(__('pilgrimage.fields.activity'))->required()->live()
                        ->options(fn (): array => DawahActivityPlan::query()->where('pilgrimage_program_id', $this->record->id)->orderBy('planned_date')->pluck('title', 'id')->all()),
                    Select::make('activity_transport_request_id')->label(__('pilgrimage.fields.transport_request'))
                        ->options(fn (Get $get): array => ActivityTransportRequest::query()->where('activity_plan_id', (int) $get('activity_plan_id'))
                            ->where('status', ActivityTransportStatus::Approved)->get()
                            ->mapWithKeys(fn (ActivityTransportRequest $request): array => [$request->id => $request->departure_at->setTimezone((string) config('app.display_timezone'))->format('Y-m-d H:i')])->all()),
                    DateTimePicker::make('pickup_at')->label(__('pilgrimage.fields.pickup_at'))->required()->seconds(false),
                ])
                ->action(function (array $data, Action $action): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        abort(403);
                    }

                    try {
                        $result = app(PilgrimPickups::class)->requestForProgram(
                            $this->record->id,
                            DawahActivityPlan::query()->findOrFail((int) $data['activity_plan_id']),
                            CarbonImmutable::parse((string) $data['pickup_at'], (string) config('app.display_timezone')),
                            blank($data['activity_transport_request_id'] ?? null) ? null : (int) $data['activity_transport_request_id'],
                            $user,
                        );
                    } catch (RuleViolation $violation) {
                        Notification::make()->danger()->title($violation->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title(__('pilgrimage.notifications.pickups_requested', $result))->send();
                }),
        ];
    }
}
