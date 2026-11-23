<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Prospects\Schemas;

use App\Domain\Companies\Models\Company;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Religion;
use App\Domain\Prospects\Enums\InterestLevel;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Support\Localization\LocalizedName;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * State only: the pages hand it to CreateProspect / UpdateProspect.
 */
final class ProspectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make(__('prospects.sections.person'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('full_name')->label(__('prospects.fields.full_name'))->required()->maxLength(255),
                Select::make('gender')->label(__('prospects.fields.gender'))->required()
                    ->options(self::options(Gender::cases())),
                Select::make('nationality_id')->label(__('prospects.fields.nationality'))->searchable()
                    ->options(fn (): array => LocalizedName::options(Country::query())),
                Select::make('native_language_id')->label(__('prospects.fields.native_language'))->searchable()
                    ->options(fn (): array => LocalizedName::options(Language::query())),
                Select::make('current_religion_id')->label(__('prospects.fields.current_religion'))
                    ->options(fn (): array => LocalizedName::options(Religion::query()->where('active', true)->orderBy('sort_order'), sortByName: false)),
                TextInput::make('current_religion_other')->label(__('prospects.fields.current_religion_other'))->maxLength(255),
                TextInput::make('occupation')->label(__('prospects.fields.occupation'))->maxLength(255),
                Select::make('company_id')->label(__('companies.model'))->searchable()
                    ->options(fn (): array => LocalizedName::options(Company::query())),
            ]),
            Section::make(__('prospects.sections.contact'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('phone')->label(__('prospects.fields.phone'))->tel()->maxLength(30)
                    ->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('email')->label(__('prospects.fields.email'))->email()->maxLength(255)
                    ->extraInputAttributes(['dir' => 'ltr']),
                Checkbox::make('confirm_possible_duplicate')->label(__('prospects.fields.confirm_possible_duplicate'))
                    ->columnSpanFull()->dehydrated(),
                Select::make('residence_city_id')->label(__('prospects.fields.residence_city'))->searchable()
                    ->options(fn (): array => LocalizedName::options(City::query())),
                TextInput::make('residence_description')->label(__('prospects.fields.residence_description'))->maxLength(255),
            ]),
            Section::make(__('prospects.sections.dawah'))->columns(2)->columnSpanFull()->schema([
                Select::make('source_type')->label(__('prospects.fields.source_type'))->required()
                    ->options(self::options(ProspectSource::cases())),
                TextInput::make('source_description')->label(__('prospects.fields.source_description'))->maxLength(255),
                Select::make('interest_level')->label(__('prospects.fields.interest_level'))
                    ->options(self::options(InterestLevel::cases())),
                // Only when creating, and only for those who choose the da'i; later changes use "Assign da'i".
                Select::make('primary_dai_id')->label(__('prospects.fields.primary_dai'))->searchable()->required()
                    ->options(fn (): array => app(DaiDirectory::class)->activeOptions())
                    ->visibleOn('create')
                    ->visible(fn (): bool => auth()->user()?->hasPermission('prospects.assign_dai') ?? false),
            ]),
        ]);
    }

    /**
     * @param  list<Gender|ProspectSource|InterestLevel>  $cases
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
