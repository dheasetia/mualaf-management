<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Companies\Models\Company;
use App\Domain\Converts\Actions\SaveConvertLocationRecord;
use App\Domain\Converts\Enums\ContactRole;
use App\Domain\Converts\Enums\DisclosureScope;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContactPerson;
use App\Domain\Platform\Models\Language;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class ContactPersonsRelationManager extends LocationRelationManager
{
    protected static string $relationship = 'contactPersons';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('converts.locations.contact_persons');
    }

    protected static function recordClass(): string
    {
        return ConvertContactPerson::class;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextInput::make('name')->label(__('converts.locations.name'))->required()->maxLength(255),
                    TextInput::make('position')->label(__('converts.locations.position'))->maxLength(255),
                    Select::make('contact_role')->label(__('converts.locations.contact_role'))->required()
                        ->options(collect(ContactRole::cases())->mapWithKeys(fn (ContactRole $case): array => [$case->value => $case->label()])->all()),
                    Select::make('company_id')->label(__('converts.locations.company'))->searchable()
                        ->options(fn (): array => LocalizedName::options(Company::query())),
                ]),
                Fieldset::make(__('forms.contact'))->columns(2)->schema([
                    TextInput::make('phone_encrypted')->label(__('converts.locations.phone'))->tel()->maxLength(30),
                    TextInput::make('alternate_phone_encrypted')->label(__('converts.locations.alternate_phone'))->tel()->maxLength(30),
                    TextInput::make('email')->label(__('converts.locations.email'))->email()->maxLength(255),
                    Select::make('preferred_language_id')->label(__('converts.locations.preferred_language'))->searchable()
                        ->options(fn (): array => LocalizedName::options(Language::query())),
                ]),
                Fieldset::make(__('forms.consent'))->columns(2)->schema([
                    Toggle::make('permission_to_contact')->label(__('converts.locations.permission_to_contact')),
                    Select::make('disclosure_scope')->label(__('converts.locations.disclosure_scope'))->required()
                        ->options(collect(DisclosureScope::cases())->mapWithKeys(fn (DisclosureScope $case): array => [$case->value => $case->label()])->all())->default(DisclosureScope::None->value),
                    Toggle::make('is_primary')->label(__('converts.locations.is_primary')),
                ]),
                Fieldset::make(__('forms.notes'))->columns(1)->schema([
                    Textarea::make('notes')->label(__('converts.locations.notes'))->maxLength(2000)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('converts.locations.name')),
                TextColumn::make('contact_role')->label(__('converts.locations.contact_role'))
                    ->formatStateUsing(fn (ContactRole $state): string => $state->label()),
                IconColumn::make('permission_to_contact')->label(__('converts.locations.permission_to_contact'))->boolean(),
                TextColumn::make('disclosure_scope')->label(__('converts.locations.disclosure_scope'))->badge()
                    ->formatStateUsing(fn (DisclosureScope $state): string => $state->label())
                    ->color(fn (DisclosureScope $state): string => $state === DisclosureScope::KnowsConversion ? 'warning' : 'gray'),
                IconColumn::make('is_primary')->label(__('converts.locations.is_primary'))->boolean(),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([
                $this->editAction(),
                DeleteAction::make()->using(function (Model $record): bool {
                    $owner = $this->getOwnerRecord();
                    $user = auth()->user();

                    if ($owner instanceof Convert && $user instanceof User && $record instanceof ConvertContactPerson) {
                        app(SaveConvertLocationRecord::class)->deleteContactPerson($owner, $record, $user);
                    }

                    return true;
                }),
            ]);
    }
}
