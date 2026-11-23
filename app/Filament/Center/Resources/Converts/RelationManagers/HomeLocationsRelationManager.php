<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\International\Actions\HomeCountryRecords;
use App\Domain\International\Models\ConvertHomeLocation;
use App\Domain\Platform\Models\Country;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class HomeLocationsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'homeLocations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('international.home_locations.plural');
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
        $form = [
            Select::make('country_id')->label(__('international.fields.country'))->required()->searchable()
                ->options(fn (): array => Country::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
            TextInput::make('province_state')->label(__('international.fields.province_state'))->maxLength(255),
            TextInput::make('city')->label(__('international.fields.city'))->maxLength(255),
            TextInput::make('district')->label(__('international.fields.district'))->maxLength(255),
            TextInput::make('village')->label(__('international.fields.village'))->maxLength(255),
            TextInput::make('address')->label(__('international.fields.address'))->maxLength(500),
            TextInput::make('latitude')->label(__('international.fields.latitude'))->numeric()->minValue(-90)->maxValue(90),
            TextInput::make('longitude')->label(__('international.fields.longitude'))->numeric()->minValue(-180)->maxValue(180),
            TextInput::make('google_maps_url')->label(__('international.fields.google_maps_url'))->url()->maxLength(500),
            Toggle::make('is_primary')->label(__('international.fields.is_primary')),
            Textarea::make('notes')->label(__('international.fields.notes'))->maxLength(2000),
        ];

        return $table
            ->columns([
                TextColumn::make('city')->label(__('international.fields.city'))->placeholder('—'),
                TextColumn::make('province_state')->label(__('international.fields.province_state'))->placeholder('—'),
                TextColumn::make('address')->label(__('international.fields.address'))->limit(40)->placeholder('—'),
                IconColumn::make('is_primary')->label(__('international.fields.is_primary'))->boolean(),
            ])
            ->headerActions([
                Action::make('add')->label(__('international.actions.add'))->visible(fn (): bool => $this->canRecord())->schema($form)
                    ->action(self::domainAction(fn (mixed $row, array $data, User $user) => app(HomeCountryRecords::class)->saveLocation($this->convert(), null, $data, $user), 'international.notifications.saved')),
            ])
            ->recordActions([
                Action::make('edit')->label(__('filament-actions::edit.single.label'))->visible(fn (): bool => $this->canRecord())->schema($form)
                    ->fillForm(fn (ConvertHomeLocation $record): array => $record->attributesToArray())
                    ->action(self::domainAction(fn (ConvertHomeLocation $record, array $data, User $user) => app(HomeCountryRecords::class)->saveLocation($this->convert(), $record, $data, $user), 'international.notifications.saved')),
            ]);
    }
}
