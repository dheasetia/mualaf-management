<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\Schemas;

use App\Domain\Converts\Enums\ContactType;
use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Enums\ProfileAccess;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContact;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Enums\FollowUpType;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Dai\Services\FollowUpHistory;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Transfers\Services\TransferLabels;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Sections follow ConvertAccess: users with the minimal profile of a
 * restricted convert see the display name, status and primary da'i only.
 */
final class ConvertInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = (string) config('app.display_timezone');

        return $schema->columns(2)->components([
            Section::make(__('converts.sections.profile'))->columns(2)->columnSpanFull()->schema([
                TextEntry::make('display_name')->label(__('converts.fields.display_name'))
                    ->state(fn (Convert $record): string => self::access()?->displayName(self::user(), $record) ?? $record->alias()),
                TextEntry::make('status')->label(__('converts.fields.status'))->badge()
                    ->formatStateUsing(fn (ConvertStatus $state): string => $state->label())
                    ->color(fn (ConvertStatus $state): string => $state->color()),
                TextEntry::make('disclosure_risk_level')->label(__('converts.fields.disclosure_risk_level'))->badge()
                    ->formatStateUsing(fn (DisclosureLevel $state): string => $state->label())
                    ->color(fn (DisclosureLevel $state): string => $state->color()),
                TextEntry::make('primary_dai_id')->label(__('converts.fields.primary_dai'))
                    ->formatStateUsing(fn (int $state): string => app(DaiDirectory::class)->names([$state])[$state] ?? '—'),
                ...array_map(fn ($entry) => $entry->visible(fn (Convert $record): bool => self::full($record)), [
                    TextEntry::make('name_ar')->label(__('converts.fields.name_ar'))->placeholder('—'),
                    TextEntry::make('islamic_name')->label(__('converts.fields.islamic_name'))->placeholder('—'),
                    TextEntry::make('previous_name')->label(__('converts.fields.previous_name'))->placeholder('—'),
                    TextEntry::make('gender')->label(__('converts.fields.gender'))
                        ->formatStateUsing(fn (Gender $state): string => $state->label()),
                    TextEntry::make('date_of_birth')->label(__('converts.fields.date_of_birth'))->date('Y-m-d')->placeholder('—'),
                    TextEntry::make('nationality.'.LocalizedName::column())->label(__('converts.fields.nationality')),
                    TextEntry::make('nativeLanguage.'.LocalizedName::column())->label(__('converts.fields.native_language'))->placeholder('—'),
                ]),
            ]),
            Section::make(__('converts.sections.sensitive'))->columns(2)->columnSpanFull()
                ->visible(fn (Convert $record): bool => self::access()?->canSeeSensitive(self::user(), $record) ?? false)
                ->schema([
                    TextEntry::make('previousReligion.'.LocalizedName::column())->label(__('converts.fields.previous_religion'))->placeholder('—'),
                    TextEntry::make('previous_religion_other')->label(__('converts.fields.previous_religion_other'))->placeholder('—'),
                ]),
            Section::make(__('converts.sections.identifiers'))->columnSpanFull()
                ->visible(fn (Convert $record): bool => self::full($record))
                ->schema([
                    RepeatableEntry::make('identifiers_list')->hiddenLabel()->columns(4)
                        ->state(fn (Convert $record): array => $record->identifiers()->whereNull('replaced_at')->with('issuingCountry')->get()->all())
                        ->schema([
                            TextEntry::make('identity_type')->label(__('converts.fields.identity_type'))
                                ->formatStateUsing(fn (IdentityType $state): string => $state->label()),
                            TextEntry::make('masked')->label(__('converts.fields.identity_number'))
                                ->state(fn (ConvertIdentifier $record): string => $record->masked())->fontFamily('mono'),
                            TextEntry::make('issuingCountry.'.LocalizedName::column())->label(__('converts.fields.issuing_country')),
                            IconEntry::make('is_primary')->label(__('converts.fields.is_primary'))->boolean(),
                        ]),
                ]),
            Section::make(__('converts.sections.contacts'))->columnSpanFull()
                ->visible(fn (Convert $record): bool => self::user()->can('viewContacts', $record))
                ->schema([
                    RepeatableEntry::make('contacts_list')->hiddenLabel()->columns(3)
                        ->state(fn (Convert $record): array => $record->contacts()->where('active', true)->get()->all())
                        ->schema([
                            TextEntry::make('contact_type')->label(__('converts.fields.contact_type'))
                                ->formatStateUsing(fn (ContactType $state): string => $state->label()),
                            TextEntry::make('value')->label(__('converts.fields.value'))
                                ->state(fn (ConvertContact $record): string => $record->value_encrypted)
                                ->extraAttributes(['dir' => 'ltr']),
                            IconEntry::make('is_primary')->label(__('converts.fields.is_primary'))->boolean(),
                        ]),
                ]),
            Section::make(__('prospects.sections.follow_ups'))->columnSpanFull()
                ->visible(fn (Convert $record): bool => self::user()->can('viewFollowUps', $record))
                ->schema([
                    RepeatableEntry::make('follow_ups_list')->hiddenLabel()->columns(4)
                        ->state(fn (Convert $record): array => array_values(app(FollowUpHistory::class)->forSubject($record, self::user())->all()))
                        ->schema([
                            TextEntry::make('occurred_at')->label(__('prospects.fields.occurred_at'))->dateTime('Y-m-d H:i', timezone: $timezone),
                            TextEntry::make('dai.full_name')->label(__('prospects.fields.dai'))->placeholder('—'),
                            TextEntry::make('follow_up_type')->label(__('prospects.fields.follow_up_type'))
                                ->formatStateUsing(fn (FollowUpType $state): string => $state->label()),
                            TextEntry::make('confidentiality_level')->label(__('prospects.fields.confidentiality_level'))->badge()
                                ->formatStateUsing(fn (Confidentiality $state): string => $state->label()),
                            TextEntry::make('summary')->label(__('prospects.fields.summary'))->columnSpanFull(),
                        ]),
                ]),
            Section::make(__('transfers.convert_plural'))->columnSpanFull()->collapsed()
                ->visible(fn (Convert $record): bool => self::full($record) && app(TransferLabels::class)->completedFor($record) !== [])
                ->schema([
                    RepeatableEntry::make('transfers_list')->hiddenLabel()->columns(4)
                        ->state(fn (Convert $record): array => app(TransferLabels::class)->completedFor($record))
                        ->schema([
                            TextEntry::make('completed_at')->label(__('transfers.fields.transferred_at'))->dateTime('Y-m-d', timezone: $timezone),
                            TextEntry::make('fromTenant.'.LocalizedName::column())->label(__('transfers.fields.from')),
                            TextEntry::make('toTenant.'.LocalizedName::column())->label(__('transfers.fields.to')),
                            TextEntry::make('reason')->label(__('transfers.fields.reason')),
                        ]),
                ]),
            Section::make(__('converts.sections.history'))->columnSpanFull()->collapsed()
                ->visible(fn (Convert $record): bool => self::full($record))
                ->schema([
                    RepeatableEntry::make('history_list')->hiddenLabel()->columns(4)
                        ->state(fn (Convert $record): array => $record->statusHistory()->with('changedBy')->latest('created_at')->get()->all())
                        ->schema([
                            TextEntry::make('created_at')->label(__('converts.fields.changed_at'))->dateTime('Y-m-d H:i', timezone: $timezone),
                            TextEntry::make('to_status')->label(__('converts.fields.status'))->badge()
                                ->formatStateUsing(fn (ConvertStatus $state): string => $state->label()),
                            TextEntry::make('changedBy.name')->label(__('converts.fields.changed_by')),
                            TextEntry::make('reason')->label(__('converts.fields.reason'))->placeholder('—'),
                        ]),
                ]),
        ]);
    }

    private static function user(): User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : new User;
    }

    private static function access(): ?ConvertAccess
    {
        return auth()->user() instanceof User ? app(ConvertAccess::class) : null;
    }

    private static function full(Convert $record): bool
    {
        return self::access()?->profile(self::user(), $record) === ProfileAccess::Full;
    }
}
