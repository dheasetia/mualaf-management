<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\Schemas;

use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Religion;
use App\Support\Localization\LocalizedName;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ConvertForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make(__('converts.sections.profile'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('full_name')->label(__('converts.fields.full_name'))->required()->maxLength(255),
                TextInput::make('name_ar')->label(__('converts.fields.name_ar'))->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('islamic_name')->label(__('converts.fields.islamic_name'))->maxLength(255),
                TextInput::make('previous_name')->label(__('converts.fields.previous_name'))->maxLength(255),
                Select::make('gender')->label(__('converts.fields.gender'))->required()
                    ->options(collect(Gender::cases())->mapWithKeys(fn (Gender $gender): array => [$gender->value => $gender->label()])->all()),
                DatePicker::make('date_of_birth')->label(__('converts.fields.date_of_birth'))->maxDate(fn (): string => CarbonImmutable::now((string) config('app.display_timezone'))->toDateString()),
                Select::make('nationality_id')->label(__('converts.fields.nationality'))->required()->searchable()
                    ->options(fn (): array => LocalizedName::options(Country::query())),
                Select::make('native_language_id')->label(__('converts.fields.native_language'))->searchable()
                    ->options(fn (): array => LocalizedName::options(Language::query())),
            ]),
            Section::make(__('converts.sections.sensitive'))->columns(2)->columnSpanFull()
                ->visible(fn (): bool => auth()->user()?->hasPermission('converts.view_sensitive') ?? false)
                ->schema([
                    Select::make('previous_religion_id')->label(__('converts.fields.previous_religion'))
                        ->options(fn (): array => LocalizedName::options(Religion::query()->orderBy('sort_order'), sortByName: false)),
                    TextInput::make('previous_religion_other')->label(__('converts.fields.previous_religion_other'))->maxLength(255),
                ]),
        ]);
    }
}
