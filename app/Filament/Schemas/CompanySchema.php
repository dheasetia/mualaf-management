<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use App\Domain\Companies\Actions\SaveCompany;
use App\Domain\Companies\Models\Company;
use App\Domain\Platform\Models\City;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Geo\GoogleMapsLink;
use App\Support\Localization\LocalizedName;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Validation\ValidationException;

/**
 * Company form and columns shared by the Center and Central company screens.
 */
final class CompanySchema
{
    /** @return list<Component> */
    public static function form(): array
    {
        return [
            Section::make()->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextInput::make('name_ar')->label(__('companies.fields.name_ar'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                    TextInput::make('name_en')->label(__('companies.fields.name_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('registration_number')->label(__('companies.fields.registration_number'))->maxLength(40),
                ]),
                Fieldset::make(__('forms.contact'))->columns(2)->schema([
                    TextInput::make('phone')->label(__('companies.fields.phone'))->tel()->maxLength(30)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('email')->label(__('companies.fields.email'))->email()->maxLength(255),
                    TextInput::make('website')->label(__('companies.fields.website'))->url()->maxLength(255),
                ]),
                Fieldset::make(__('forms.location'))->columns(2)->schema([
                    Select::make('city_id')->label(__('companies.fields.city'))->searchable()
                        ->options(fn (): array => LocalizedName::options(City::query())),
                    TextInput::make('district')->label(__('companies.fields.district'))->maxLength(255),
                    TextInput::make('address')->label(__('companies.fields.address'))->maxLength(255),
                    TextInput::make('postal_code')->label(__('companies.fields.postal_code'))->maxLength(10),
                    TextInput::make('google_maps_url')->label(__('companies.fields.google_maps_url'))->url()->maxLength(500)
                        ->helperText(__('companies.help.google_maps_url'))->columnSpanFull()->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('latitude')->label(__('companies.fields.latitude'))->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('longitude')->label(__('companies.fields.longitude'))->numeric()->minValue(-180)->maxValue(180),
                ]),
                Fieldset::make(__('forms.notes'))->columns(1)->schema([
                    Textarea::make('notes')->label(__('companies.fields.notes'))->maxLength(2000)->columnSpanFull(),
                ]),
            ]),
        ];
    }

    /** @return list<Column> */
    public static function columns(): array
    {
        return [
            TextColumn::make('name_ar')->label(__('companies.fields.name_ar'))->searchable()->sortable()
                ->description(fn (Company $record): ?string => $record->name_en),
            TextColumn::make('registration_number')->label(__('companies.fields.registration_number'))->searchable(),
            TextColumn::make('city.'.LocalizedName::column())->label(__('companies.fields.city'))->placeholder('—'),
            TextColumn::make('owner')->label(__('companies.fields.owner'))->badge()
                ->state(fn (Company $record): string => $record->isNational() ? __('companies.owner.national') : ($record->tenant->code ?? __('companies.owner.office')))
                ->color(fn (Company $record): string => $record->isNational() ? 'success' : 'gray'),
        ];
    }

    public static function mapAction(): Action
    {
        return Action::make('map')->label(__('companies.actions.open_map'))->icon('heroicon-o-map-pin')
            ->url(fn (Company $record): ?string => GoogleMapsLink::forCoordinates($record->latitude, $record->longitude) ?? $record->google_maps_url, shouldOpenInNewTab: true)
            ->visible(fn (Company $record): bool => $record->latitude !== null || $record->google_maps_url !== null);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?Company $company, array $data): Company
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(SaveCompany::class)->handle($company, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }
}
