<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms\RelationManagers;

use App\Domain\Pilgrimage\Actions\ManagePrograms;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Models\PilgrimageGroup;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class GroupsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'groups';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pilgrimage.groups.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $program = $this->getOwnerRecord();
        $manages = fn (): bool => auth()->user()?->hasPermission('pilgrimage.programs.manage') ?? false;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('members'))
            ->columns([
                TextColumn::make('group_name')->label(__('pilgrimage.fields.group_name')),
                TextColumn::make('leader')->label(__('pilgrimage.fields.leader'))
                    ->state(fn (PilgrimageGroup $record): string => (string) User::query()->whereKey($record->leader_user_id)->value('name')),
                TextColumn::make('members')->label(__('pilgrimage.fields.members'))->state(fn (PilgrimageGroup $record): string => $record->getAttribute('members_count').' / '.$record->capacity),
            ])
            ->headerActions([
                Action::make('addGroup')->label(__('pilgrimage.actions.add_group'))->visible($manages)
                    ->schema([
                        TextInput::make('group_name')->label(__('pilgrimage.fields.group_name'))->required()->maxLength(255),
                        Select::make('leader_user_id')->label(__('pilgrimage.fields.leader'))->required()->searchable()
                            ->options(fn (): array => User::query()->whereIn('id', UserTenantMembership::query()->where('tenant_id', app(TenantContext::class)->id())->select('user_id'))
                                ->orderBy('name')->pluck('name', 'id')->all()),
                        TextInput::make('capacity')->label(__('pilgrimage.fields.capacity'))->required()->integer()->minValue(1),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ManagePrograms::class)->createGroup(
                        $program instanceof PilgrimageProgram ? $program : abort(404), (string) $data['group_name'], User::query()->findOrFail((int) $data['leader_user_id']), (int) $data['capacity'], $user,
                    ), 'pilgrimage.notifications.saved')),
            ])
            ->recordActions([
                Action::make('addMember')->label(__('pilgrimage.actions.add_member'))->visible($manages)
                    ->schema([
                        Select::make('registration_id')->label(__('pilgrimage.fields.convert'))->required()->searchable()
                            ->options(fn (): array => $program instanceof PilgrimageProgram ? $program->registrations()
                                ->whereIn('status', [RegistrationStatus::Approved, RegistrationStatus::Confirmed])->get()
                                ->mapWithKeys(fn (PilgrimageRegistration $registration): array => [$registration->id => RegistrationsRelationManager::convertName($registration->convert_id)])->all() : []),
                    ])
                    ->action(self::domainAction(fn (PilgrimageGroup $record, array $data, User $user) => app(ManagePrograms::class)->addToGroup(
                        $record, PilgrimageRegistration::query()->findOrFail((int) $data['registration_id']), $user,
                    ), 'pilgrimage.notifications.saved')),
            ]);
    }
}
