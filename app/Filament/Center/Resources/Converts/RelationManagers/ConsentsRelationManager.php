<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\International\Actions\Consents;
use App\Domain\International\Enums\ConsentMethod;
use App\Domain\International\Enums\ConsentType;
use App\Domain\International\Enums\SharedField;
use App\Domain\International\Models\ConsentRecord;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use App\Support\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class ConsentsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'consents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('international.consents.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Convert && (auth()->user()?->can('viewFollowUps', $ownerRecord) ?? false);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function convert(): Convert
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Convert ? $owner : abort(404);
    }

    private function canRecord(): bool
    {
        return auth()->user()?->can('recordFollowUp', $this->convert()) ?? false;
    }

    public function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');

        return $table
            ->columns([
                TextColumn::make('consent_type')->label(__('international.fields.consent_type'))->formatStateUsing(fn (ConsentType $state): string => $state->label()),
                TextColumn::make('scope')->label(__('international.fields.fields'))
                    ->state(fn (ConsentRecord $record): string => collect($record->fields())->map(fn (string $field): string => SharedField::tryFrom($field)?->label() ?? $field)->implode('، ') ?: '—'),
                TextColumn::make('given_at')->label(__('international.fields.given_at'))->date('Y-m-d', $timezone),
                TextColumn::make('method')->label(__('international.fields.method'))->formatStateUsing(fn (ConsentMethod $state): string => $state->label()),
                TextColumn::make('expires_at')->label(__('international.fields.expires_at'))->date('Y-m-d', $timezone)->placeholder('—'),
                IconColumn::make('active')->label(__('international.fields.status'))->boolean()->state(fn (ConsentRecord $record): bool => $record->isActive()),
            ])
            ->headerActions([
                Action::make('recordConsent')->label(__('international.actions.record_consent'))->visible(fn (): bool => $this->canRecord())
                    ->schema([
                        Select::make('consent_type')->label(__('international.fields.consent_type'))->required()->live()->options(collect(ConsentType::cases())->mapWithKeys(fn (ConsentType $case): array => [$case->value => $case->label()])->all()),
                        CheckboxList::make('fields')->label(__('international.fields.fields'))->columns(2)
                            ->visible(fn (Get $get): bool => $get('consent_type') === ConsentType::InternationalSharing->value)
                            ->options(collect(SharedField::cases())->mapWithKeys(fn (SharedField $case): array => [$case->value => $case->label()])->all()),
                        Select::make('destination_country_id')->label(__('international.fields.destination_country'))->searchable()
                            ->visible(fn (Get $get): bool => $get('consent_type') === ConsentType::InternationalSharing->value)
                            ->options(fn (): array => LocalizedName::options(Country::query())),
                        Select::make('method')->label(__('international.fields.method'))->required()->live()->options(collect(ConsentMethod::cases())->mapWithKeys(fn (ConsentMethod $case): array => [$case->value => $case->label()])->all()),
                        Select::make('witnessed_by')->label(__('international.fields.witnessed_by'))->searchable()
                            ->visible(fn (Get $get): bool => $get('method') === ConsentMethod::VerbalWitnessed->value)
                            ->options(fn (): array => User::query()->whereIn('id', UserTenantMembership::query()->where('tenant_id', app(TenantContext::class)->id())->select('user_id'))
                                ->whereKeyNot(auth()->id())->orderBy('name')->pluck('name', 'id')->all()),
                        DateTimePicker::make('given_at')->label(__('international.fields.given_at'))->seconds(false)->default(now()),
                        DateTimePicker::make('expires_at')->label(__('international.fields.expires_at'))->seconds(false),
                    ])
                    ->action(self::domainAction(fn (mixed $row, array $data, User $user) => app(Consents::class)->record($this->convert(), $data, $user), 'international.notifications.saved')),
            ])
            ->recordActions([
                Action::make('withdraw')->label(__('international.actions.withdraw'))->color('danger')
                    ->visible(fn (ConsentRecord $record): bool => $record->withdrawn_at === null && $this->canRecord())
                    ->schema([Textarea::make('reason')->label(__('international.fields.reason'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (ConsentRecord $record, array $data, User $user) => app(Consents::class)->withdraw($record, $data['reason'] ?? null, $user), 'international.notifications.saved')),
            ]);
    }
}
