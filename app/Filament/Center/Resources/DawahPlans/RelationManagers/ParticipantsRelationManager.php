<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahPlans\RelationManagers;

use App\Domain\Dawah\Actions\ManageParticipants;
use App\Domain\Dawah\Enums\AttendanceStatus;
use App\Domain\Dawah\Enums\ParticipantType;
use App\Domain\Dawah\Models\ActivityParticipant;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Domain\Dawah\Services\ParticipantDirectory;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Participants of a plan, named under the viewer's visibility.
 */
final class ParticipantsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'participants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('dawah.participants.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $plan = $this->getOwnerRecord();
        $manages = fn (): bool => ($user = auth()->user()) instanceof User && $plan instanceof DawahActivityPlan
            && ($user->hasPermission('dawah.plans.approve') || ($user->hasPermission('dawah.plans.create') && app(DawahActors::class)->isPlanDai($user, $plan)));
        $manage = fn (): ManageParticipants => app(ManageParticipants::class);

        return $table
            ->columns([
                TextColumn::make('name')->label(__('dawah.fields.participant'))
                    ->state(fn (ActivityParticipant $record): string => ($user = auth()->user()) instanceof User ? app(ParticipantDirectory::class)->name($record, $user) : '—'),
                TextColumn::make('participant_type')->label(__('dawah.fields.participant_type'))->formatStateUsing(fn (ParticipantType $state): string => $state->label()),
                TextColumn::make('attendance_status')->label(__('dawah.fields.attendance'))->badge()->formatStateUsing(fn (AttendanceStatus $state): string => $state->label()),
                IconColumn::make('transportation_required')->label(__('dawah.fields.transportation_required'))->boolean(),
            ])
            ->headerActions([
                Action::make('add')->label(__('dawah.actions.add_participant'))->visible($manages)
                    ->schema([
                        Section::make()->columnSpanFull()->schema([
                            Fieldset::make(__('forms.basic'))->columns(2)->schema([
                                Select::make('participant_type')->label(__('dawah.fields.participant_type'))->required()->live()
                                    ->options(collect(ParticipantType::cases())->mapWithKeys(fn (ParticipantType $type): array => [$type->value => $type->label()])->all()),
                                Select::make('participant_id')->label(__('dawah.fields.participant'))->searchable()->required()
                                    ->visible(fn (Get $get): bool => ParticipantType::tryFrom((string) $get('participant_type'))?->isLinked() ?? false)
                                    ->options(fn (Get $get): array => ($type = ParticipantType::tryFrom((string) $get('participant_type'))) !== null && ($user = auth()->user()) instanceof User
                                        ? app(ParticipantDirectory::class)->options($type, $user) : []),
                                TextInput::make('participant_name')->label(__('dawah.fields.participant_name'))->required()->maxLength(255)
                                    ->visible(fn (Get $get): bool => ! (ParticipantType::tryFrom((string) $get('participant_type'))?->isLinked() ?? true)),
                                TextInput::make('phone')->label(__('dawah.fields.phone'))->tel()->maxLength(30)
                                    ->visible(fn (Get $get): bool => ! (ParticipantType::tryFrom((string) $get('participant_type'))?->isLinked() ?? true)),
                            ]),
                            Fieldset::make(__('forms.details'))->columns(1)->schema([
                                Toggle::make('transportation_required')->label(__('dawah.fields.transportation_required')),
                                TextInput::make('notes')->label(__('dawah.fields.notes'))->maxLength(1000),
                            ]),
                        ]),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => $manage()->add(
                        $plan instanceof DawahActivityPlan ? $plan : abort(404),
                        [
                            'participant_type' => (string) $data['participant_type'],
                            'participant_id' => blank($data['participant_id'] ?? null) ? null : (int) $data['participant_id'],
                            'participant_name' => blank($data['participant_name'] ?? null) ? null : (string) $data['participant_name'],
                            'phone' => blank($data['phone'] ?? null) ? null : (string) $data['phone'],
                            'transportation_required' => (bool) ($data['transportation_required'] ?? false),
                            'notes' => blank($data['notes'] ?? null) ? null : (string) $data['notes'],
                        ],
                        $user,
                    ), 'dawah.notifications.saved')),
            ])
            ->recordActions([
                Action::make('attendance')->label(__('dawah.actions.attendance'))->visible($manages)
                    ->schema([
                        Select::make('attendance_status')->label(__('dawah.fields.attendance'))->required()
                            ->options(collect(AttendanceStatus::cases())->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $status->label()])->all()),
                    ])
                    ->action(self::domainAction(fn (ActivityParticipant $record, array $data, User $user) => $manage()
                        ->markAttendance($record, AttendanceStatus::from((string) $data['attendance_status']), $user), 'dawah.notifications.saved')),
                Action::make('remove')->label(__('filament-actions::delete.single.label'))->color('danger')->requiresConfirmation()->visible($manages)
                    ->action(self::domainAction(fn (ActivityParticipant $record, array $data, User $user) => $manage()->remove($record, $user), 'dawah.notifications.saved')),
            ]);
    }
}
