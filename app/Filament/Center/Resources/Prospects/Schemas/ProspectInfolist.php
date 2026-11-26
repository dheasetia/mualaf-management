<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Prospects\Schemas;

use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\FollowUpType;
use App\Domain\Dai\Models\DaiFollowUp;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Prospects\Enums\InterestLevel;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ProspectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = (string) config('app.display_timezone');

        return $schema->columns(2)->components([
            Section::make(__('prospects.model'))->columnSpanFull()->schema([
                Fieldset::make(__('prospects.sections.person'))->columns(2)->schema([
                    TextEntry::make('full_name')->label(__('prospects.fields.full_name')),
                    TextEntry::make('gender')->label(__('prospects.fields.gender'))
                        ->formatStateUsing(fn (Gender $state): string => $state->label()),
                    TextEntry::make('nationality.'.LocalizedName::column())->label(__('prospects.fields.nationality'))->placeholder('—'),
                    TextEntry::make('nativeLanguage.'.LocalizedName::column())->label(__('prospects.fields.native_language'))->placeholder('—'),
                    TextEntry::make('currentReligion.'.LocalizedName::column())->label(__('prospects.fields.current_religion'))->placeholder('—'),
                    TextEntry::make('occupation')->label(__('prospects.fields.occupation'))->placeholder('—'),
                    TextEntry::make('phone')->label(__('prospects.fields.phone'))
                        ->state(fn (Prospect $record): ?string => $record->phone_encrypted)->placeholder('—'),
                    TextEntry::make('email')->label(__('prospects.fields.email'))
                        ->state(fn (Prospect $record): ?string => $record->email_encrypted)->placeholder('—'),
                    TextEntry::make('residenceCity.'.LocalizedName::column())->label(__('prospects.fields.residence_city'))->placeholder('—'),
                ]),
                Fieldset::make(__('prospects.sections.dawah'))->columns(2)->schema([
                    TextEntry::make('status')->label(__('prospects.fields.status'))->badge()
                        ->formatStateUsing(fn (ProspectStatus $state): string => $state->label())
                        ->color(fn (ProspectStatus $state): string => $state->color()),
                    TextEntry::make('primary_dai_id')->label(__('prospects.fields.primary_dai'))
                        ->formatStateUsing(fn (int $state): string => app(DaiDirectory::class)->names([$state])[$state] ?? '—'),
                    TextEntry::make('source_type')->label(__('prospects.fields.source_type'))
                        ->formatStateUsing(fn (ProspectSource $state): string => $state->label()),
                    TextEntry::make('interest_level')->label(__('prospects.fields.interest_level'))
                        ->formatStateUsing(fn (InterestLevel $state): string => $state->label())->placeholder('—'),
                    TextEntry::make('first_contact_at')->label(__('prospects.fields.first_contact_at'))
                        ->dateTime('Y-m-d H:i', timezone: $timezone)->placeholder('—'),
                    TextEntry::make('last_contact_at')->label(__('prospects.fields.last_contact_at'))
                        ->dateTime('Y-m-d H:i', timezone: $timezone)->placeholder('—'),
                    TextEntry::make('next_follow_up_at')->label(__('prospects.fields.next_follow_up_at'))
                        ->dateTime('Y-m-d H:i', timezone: $timezone)->placeholder('—'),
                ]),
            ]),
            Section::make(__('prospects.sections.follow_ups'))->columnSpanFull()
                ->visible(fn (Prospect $record): bool => auth()->user()?->can('viewFollowUps', $record) ?? false)
                ->schema([
                    RepeatableEntry::make('follow_ups')->hiddenLabel()
                        ->state(fn (Prospect $record): array => self::followUps($record))
                        ->columns(4)
                        ->schema([
                            TextEntry::make('occurred_at')->label(__('prospects.fields.occurred_at'))
                                ->dateTime('Y-m-d H:i', timezone: $timezone),
                            TextEntry::make('dai.full_name')->label(__('prospects.fields.dai')),
                            TextEntry::make('follow_up_type')->label(__('prospects.fields.follow_up_type'))
                                ->formatStateUsing(fn (FollowUpType $state): string => $state->label()),
                            TextEntry::make('contact_method')->label(__('prospects.fields.contact_method'))
                                ->formatStateUsing(fn (ContactMethod $state): string => $state->label()),
                            TextEntry::make('summary')->label(__('prospects.fields.summary'))->columnSpan(3),
                            TextEntry::make('confidentiality_level')->label(__('prospects.fields.confidentiality_level'))->badge()
                                ->formatStateUsing(fn (Confidentiality $state): string => $state->label())
                                ->color(fn (Confidentiality $state): string => $state === Confidentiality::Confidential ? 'danger' : 'gray'),
                            TextEntry::make('next_action')->label(__('prospects.fields.next_action'))->placeholder('—')->columnSpan(2),
                            TextEntry::make('next_follow_up_at')->label(__('prospects.fields.next_follow_up_at'))
                                ->dateTime('Y-m-d H:i', timezone: $timezone)->placeholder('—'),
                        ]),
                ]),
            Section::make(__('prospects.sections.history'))->columnSpanFull()->collapsed()->schema([
                RepeatableEntry::make('statusHistory')->hiddenLabel()
                    ->state(fn (Prospect $record): array => $record->statusHistory()->with('changedBy')->latest('created_at')->get()->all())
                    ->columns(4)
                    ->schema([
                        TextEntry::make('created_at')->label(__('prospects.fields.changed_at'))
                            ->dateTime('Y-m-d H:i', timezone: $timezone),
                        TextEntry::make('to_status')->label(__('prospects.fields.status'))->badge()
                            ->formatStateUsing(fn (ProspectStatus $state): string => $state->label()),
                        TextEntry::make('changedBy.name')->label(__('prospects.fields.changed_by')),
                        TextEntry::make('reason')->label(__('prospects.fields.reason'))->placeholder('—'),
                    ]),
            ]),
        ]);
    }

    /** @return list<DaiFollowUp> */
    private static function followUps(Prospect $record): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return array_values(DaiFollowUp::query()->visibleFor($record, $user)->with('dai')->latest('occurred_at')->get()->all());
    }
}
