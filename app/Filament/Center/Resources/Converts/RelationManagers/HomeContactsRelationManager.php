<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Enums\DisclosureScope;
use App\Domain\Converts\Models\Convert;
use App\Domain\International\Actions\HomeCountryRecords;
use App\Domain\International\Enums\HomeRelationship;
use App\Domain\International\Models\ConvertHomeCountryContact;
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

final class HomeContactsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'homeContacts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('international.home_contacts.plural');
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
            TextInput::make('contact_name')->label(__('international.fields.contact_name'))->required()->maxLength(255),
            Select::make('relationship')->label(__('international.fields.relationship'))->required()->options(collect(HomeRelationship::cases())->mapWithKeys(fn (HomeRelationship $case): array => [$case->value => $case->label()])->all()),
            TextInput::make('phone')->label(__('international.fields.phone'))->tel()->maxLength(30),
            TextInput::make('whatsapp')->label(__('international.fields.whatsapp'))->tel()->maxLength(30),
            TextInput::make('email')->label(__('international.fields.email'))->email()->maxLength(255),
            TextInput::make('city')->label(__('international.fields.city'))->maxLength(255),
            TextInput::make('address')->label(__('international.fields.address'))->maxLength(500),
            Toggle::make('is_muslim')->label(__('international.fields.is_muslim')),
            Toggle::make('is_primary')->label(__('international.fields.is_primary')),
            Toggle::make('permission_to_contact')->label(__('international.fields.permission_to_contact')),
            Select::make('disclosure_scope')->label(__('international.fields.disclosure_scope'))->required()->default(DisclosureScope::None->value)->options(collect(DisclosureScope::cases())->mapWithKeys(fn (DisclosureScope $case): array => [$case->value => $case->label()])->all()),
            Textarea::make('notes')->label(__('international.fields.notes'))->maxLength(2000),
        ];
        $save = fn (?ConvertHomeCountryContact $record) => self::domainAction(fn (mixed $row, array $data, User $user) => app(HomeCountryRecords::class)
            ->saveContact($this->convert(), $record ?? ($row instanceof ConvertHomeCountryContact ? $row : null), $data, $user), 'international.notifications.saved');

        return $table
            ->columns([
                TextColumn::make('contact_name')->label(__('international.fields.contact_name')),
                TextColumn::make('relationship')->label(__('international.fields.relationship'))->formatStateUsing(fn (HomeRelationship $state): string => $state->label()),
                TextColumn::make('city')->label(__('international.fields.city'))->placeholder('—'),
                IconColumn::make('permission_to_contact')->label(__('international.fields.permission_to_contact'))->boolean(),
                TextColumn::make('disclosure_scope')->label(__('international.fields.disclosure_scope'))->formatStateUsing(fn (DisclosureScope $state): string => $state->label()),
                IconColumn::make('is_primary')->label(__('international.fields.is_primary'))->boolean(),
            ])
            ->headerActions([
                Action::make('add')->label(__('international.actions.add'))->visible(fn (): bool => $this->canRecord())->schema($form)->action($save(null)),
            ])
            ->recordActions([
                Action::make('edit')->label(__('filament-actions::edit.single.label'))->visible(fn (): bool => $this->canRecord())->schema($form)
                    ->fillForm(fn (ConvertHomeCountryContact $record): array => [
                        ...$record->only(['country_id', 'contact_name', 'city', 'address', 'is_muslim', 'is_primary', 'permission_to_contact', 'notes']),
                        'relationship' => $record->relationship->value, 'disclosure_scope' => $record->disclosure_scope->value,
                        'phone' => $record->phone_encrypted, 'whatsapp' => $record->whatsapp_encrypted, 'email' => $record->email_encrypted,
                    ])
                    ->action($save(null)),
            ]);
    }
}
