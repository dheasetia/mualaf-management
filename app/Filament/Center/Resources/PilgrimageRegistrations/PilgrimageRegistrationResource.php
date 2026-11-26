<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimageRegistrations;

use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Filament\Center\Resources\PilgrimagePrograms\RelationManagers\RegistrationsRelationManager;
use App\Filament\Center\Resources\PilgrimageRegistrations\Pages\ViewPilgrimageRegistration;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A pilgrim's registration page (requirements and documents); reached from
 * the program, not the navigation.
 */
final class PilgrimageRegistrationResource extends Resource
{
    protected static ?string $model = PilgrimageRegistration::class;

    protected static ?string $slug = 'pilgrimage-registrations';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('pilgrimage.registrations.model');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('pilgrimage.registrations.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextEntry::make('program.name')->label(__('pilgrimage.programs.model')),
                    TextEntry::make('convert')->label(__('pilgrimage.fields.convert'))
                        ->state(fn (PilgrimageRegistration $record): string => RegistrationsRelationManager::convertName($record->convert_id)),
                ]),
                Fieldset::make(__('forms.status'))->columns(2)->schema([
                    TextEntry::make('status')->label(__('pilgrimage.fields.status'))->badge()
                        ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())->color(fn (RegistrationStatus $state): string => $state->color()),
                    TextEntry::make('eligibility_notes')->label(__('pilgrimage.fields.eligibility_notes'))->placeholder('—')->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\RequirementsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['view' => ViewPilgrimageRegistration::route('/{record}')];
    }
}
