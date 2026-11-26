<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\ExternalOrganizations;

use App\Domain\International\Enums\OrganizationStatus;
use App\Domain\International\Enums\OrganizationType;
use App\Domain\International\Models\ExternalIslamicOrganization;
use App\Domain\Platform\Models\Country;
use App\Filament\Central\Resources\ExternalOrganizations\Pages\ManageExternalOrganizations;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * National directory of Islamic organizations abroad (international.organizations.manage).
 */
final class ExternalOrganizationResource extends Resource
{
    protected static ?string $model = ExternalIslamicOrganization::class;

    protected static ?string $slug = 'organizations-abroad';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 85;

    public static function getModelLabel(): string
    {
        return __('international.organizations.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('international.organizations.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('platform.navigation_group');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('international.organizations.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('international.organizations.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextInput::make('organization_name')->label(__('international.fields.organization_name'))->required()->maxLength(255),
                    Select::make('organization_type')->label(__('international.fields.organization_type'))->required()
                        ->options(collect(OrganizationType::cases())->mapWithKeys(fn (OrganizationType $case): array => [$case->value => $case->label()])->all()),
                ]),
                Fieldset::make(__('forms.location'))->columns(2)->schema([
                    Select::make('country_id')->label(__('international.fields.country'))->required()->searchable()
                        ->options(fn (): array => LocalizedName::options(Country::query())),
                    TextInput::make('city')->label(__('international.fields.city'))->required()->maxLength(255),
                    TextInput::make('address')->label(__('international.fields.address'))->maxLength(500),
                ]),
                Fieldset::make(__('forms.contact'))->columns(2)->schema([
                    TextInput::make('contact_person')->label(__('international.fields.contact_person'))->maxLength(255),
                    TextInput::make('phone')->label(__('international.fields.phone'))->tel()->maxLength(40),
                    TextInput::make('whatsapp')->label(__('international.fields.whatsapp'))->tel()->maxLength(40),
                    TextInput::make('email')->label(__('international.fields.email'))->email()->maxLength(255),
                    TextInput::make('website')->label(__('international.fields.website'))->url()->maxLength(500),
                ]),
                Fieldset::make(__('forms.notes'))->columns(1)->schema([
                    Textarea::make('notes')->label(__('international.fields.notes'))->maxLength(2000)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $decide = fn (string $name, OrganizationStatus $status): Action => Action::make($name)->label(__('international.actions.'.$name))->requiresConfirmation()
            ->visible(fn (ExternalIslamicOrganization $record): bool => $record->verification_status !== $status)
            ->action(function (ExternalIslamicOrganization $record) use ($status): void {
                $user = auth()->user();
                $record->verification_status = $status;
                $record->verified_by = $status === OrganizationStatus::Verified && $user instanceof User ? $user->id : $record->verified_by;
                $record->verified_at = $status === OrganizationStatus::Verified ? CarbonImmutable::now() : $record->verified_at;
                $record->save();
            });

        return $table
            ->defaultSort('organization_name')
            ->columns([
                TextColumn::make('organization_name')->label(__('international.fields.organization_name'))->searchable(),
                TextColumn::make('organization_type')->label(__('international.fields.organization_type'))->formatStateUsing(fn (OrganizationType $state): string => $state->label()),
                TextColumn::make('city')->label(__('international.fields.city'))->searchable(),
                TextColumn::make('verification_status')->label(__('international.fields.verification_status'))->badge()
                    ->formatStateUsing(fn (OrganizationStatus $state): string => $state->label()),
            ])
            ->filters([
                SelectFilter::make('country_id')->label(__('international.fields.country'))->searchable()
                    ->options(fn (): array => LocalizedName::options(Country::query())),
            ])
            ->recordActions([
                EditAction::make(),
                $decide('verify', OrganizationStatus::Verified)->color('success'),
                $decide('deactivate', OrganizationStatus::Inactive)->color('danger'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageExternalOrganizations::route('/')];
    }
}
