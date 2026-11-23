<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Pilgrimage\Actions\RegistrationWorkflow;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Filament\Center\Resources\PilgrimageRegistrations\PilgrimageRegistrationResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Pilgrims of a program and their workflow steps.
 */
final class RegistrationsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'registrations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pilgrimage.registrations.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function convertName(int $convertId): string
    {
        $user = auth()->user();
        $convert = Convert::query()->find($convertId);

        return $user instanceof User && $convert instanceof Convert ? app(ConvertAccess::class)->displayName($user, $convert) : '—';
    }

    public function table(Table $table): Table
    {
        $program = $this->getOwnerRecord();
        $workflow = fn (): RegistrationWorkflow => app(RegistrationWorkflow::class);
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;

        return $table
            ->columns([
                TextColumn::make('convert')->label(__('pilgrimage.fields.convert'))->state(fn (PilgrimageRegistration $record): string => self::convertName($record->convert_id)),
                TextColumn::make('status')->label(__('pilgrimage.fields.status'))->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())->color(fn (RegistrationStatus $state): string => $state->color()),
                TextColumn::make('eligibility_notes')->label(__('pilgrimage.fields.eligibility_notes'))->limit(60)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('pilgrimage.fields.status'))
                    ->options(collect(RegistrationStatus::cases())->mapWithKeys(fn (RegistrationStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->headerActions([
                Action::make('register')->label(__('pilgrimage.actions.register'))->visible(fn (): bool => $can('pilgrimage.register'))
                    ->schema([
                        Select::make('convert_id')->label(__('pilgrimage.fields.convert'))->required()->searchable()
                            ->options(function (): array {
                                $user = auth()->user();

                                return $user instanceof User ? app(ConvertAccess::class)->visible(Convert::query(), $user)->orderBy('full_name')->limit(500)->get()
                                    ->mapWithKeys(fn (Convert $convert): array => [$convert->id => app(ConvertAccess::class)->displayName($user, $convert)])->all() : [];
                            }),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => $workflow()->register(
                        $program instanceof PilgrimageProgram ? $program : abort(404), Convert::query()->findOrFail((int) $data['convert_id']), $user,
                    ), 'pilgrimage.notifications.saved')),
            ])
            ->recordActions([
                Action::make('open')->label(__('pilgrimage.actions.open'))->icon('heroicon-o-eye')
                    ->url(fn (PilgrimageRegistration $record): string => PilgrimageRegistrationResource::getUrl('view', ['record' => $record])),
                Action::make('check')->label(__('pilgrimage.actions.check'))
                    ->visible(fn (PilgrimageRegistration $record): bool => in_array($record->status, [RegistrationStatus::Applied, RegistrationStatus::EligibilityChecked], true) && $can('pilgrimage.check_eligibility'))
                    ->action(self::domainAction(fn (PilgrimageRegistration $record, array $data, User $user) => $workflow()->checkEligibility($record, $user), 'pilgrimage.notifications.saved')),
                Action::make('approve')->label(__('pilgrimage.actions.approve'))->color('success')->requiresConfirmation()
                    ->visible(fn (PilgrimageRegistration $record): bool => in_array($record->status, [RegistrationStatus::Eligible, RegistrationStatus::Waitlisted], true) && $can('pilgrimage.approve') && $record->registered_by !== auth()->id())
                    ->action(self::domainAction(fn (PilgrimageRegistration $record, array $data, User $user) => $workflow()->approve($record, $user), 'pilgrimage.notifications.saved')),
                Action::make('confirm')->label(__('pilgrimage.actions.confirm'))
                    ->visible(fn (PilgrimageRegistration $record): bool => $record->status === RegistrationStatus::Approved && $can('pilgrimage.programs.manage'))
                    ->action(self::domainAction(fn (PilgrimageRegistration $record, array $data, User $user) => $workflow()->confirm($record, $user), 'pilgrimage.notifications.saved')),
                Action::make('withdraw')->label(__('pilgrimage.actions.withdraw'))->color('danger')
                    ->visible(fn (PilgrimageRegistration $record): bool => $record->status->canTransitionTo(RegistrationStatus::Withdrawn) && ($can('pilgrimage.programs.manage') || $can('pilgrimage.register')))
                    ->schema([Textarea::make('reason')->label(__('pilgrimage.fields.reason'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (PilgrimageRegistration $record, array $data, User $user) => $workflow()->withdraw($record, $data['reason'] ?? null, $user), 'pilgrimage.notifications.saved')),
            ]);
    }
}
