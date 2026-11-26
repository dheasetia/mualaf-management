<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Companies\Models\Company;
use App\Domain\Converts\Enums\JobStatus;
use App\Domain\Converts\Models\ConvertEmployment;
use App\Domain\Platform\Models\City;
use App\Support\Geo\GoogleMapsLink;
use App\Support\Localization\LocalizedName;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class EmploymentsRelationManager extends LocationRelationManager
{
    protected static string $relationship = 'employments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('converts.locations.employments');
    }

    protected static function recordClass(): string
    {
        return ConvertEmployment::class;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    Select::make('company_id')->label(__('converts.locations.company'))->searchable()
                        ->options(fn (): array => LocalizedName::options(Company::query())),
                    TextInput::make('company_name_free')->label(__('converts.locations.company_name_free'))->maxLength(255),
                    TextInput::make('occupation')->label(__('converts.locations.occupation'))->maxLength(255),
                    TextInput::make('department')->label(__('converts.locations.department'))->maxLength(255),
                    TextInput::make('employee_number')->label(__('converts.locations.employee_number'))->maxLength(40),
                    TextInput::make('work_phone_encrypted')->label(__('converts.locations.work_phone'))->tel()->maxLength(30),
                ]),
                Fieldset::make(__('forms.location'))->columns(2)->schema([
                    TextInput::make('work_location_description')->label(__('converts.locations.work_location'))->maxLength(255),
                    Select::make('work_city_id')->label(__('converts.locations.work_city'))->searchable()
                        ->options(fn (): array => LocalizedName::options(City::query())),
                    TextInput::make('work_google_maps_url')->label(__('converts.locations.google_maps_url'))->url()->maxLength(500)
                        ->helperText(__('companies.help.google_maps_url'))->columnSpanFull(),
                    TextInput::make('work_latitude')->label(__('converts.locations.latitude'))->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('work_longitude')->label(__('converts.locations.longitude'))->numeric()->minValue(-180)->maxValue(180),
                ]),
                Fieldset::make(__('forms.status'))->columns(2)->schema([
                    Select::make('employment_status')->label(__('converts.locations.employment_status'))->required()
                        ->options(collect(JobStatus::cases())->mapWithKeys(fn (JobStatus $case): array => [$case->value => $case->label()])->all())->default(JobStatus::Employed->value),
                    Toggle::make('is_current')->label(__('converts.locations.is_current'))->default(true),
                    DatePicker::make('started_at')->label(__('converts.locations.started_at')),
                    DatePicker::make('ended_at')->label(__('converts.locations.ended_at'))->afterOrEqual('started_at'),
                ]),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('company'))
            ->defaultSort('is_current', 'desc')
            ->columns([
                TextColumn::make('company_label')->label(__('converts.locations.company'))
                    ->state(fn (ConvertEmployment $record): string => LocalizedName::of($record->company) ?? $record->company_name_free ?? '—'),
                TextColumn::make('occupation')->label(__('converts.locations.occupation'))->placeholder('—'),
                TextColumn::make('employment_status')->label(__('converts.locations.employment_status'))->badge()
                    ->formatStateUsing(fn (JobStatus $state): string => $state->label()),
                IconColumn::make('is_current')->label(__('converts.locations.is_current'))->boolean(),
                TextColumn::make('started_at')->label(__('converts.locations.started_at'))->date('Y-m-d')->placeholder('—'),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([
                Action::make('map')->label(__('companies.actions.open_map'))->icon('heroicon-o-map-pin')
                    ->url(fn (ConvertEmployment $record): ?string => GoogleMapsLink::forCoordinates($record->work_latitude, $record->work_longitude) ?? $record->work_google_maps_url, shouldOpenInNewTab: true)
                    ->visible(fn (ConvertEmployment $record): bool => $record->work_latitude !== null || $record->work_google_maps_url !== null),
                $this->editAction(),
            ]);
    }
}
