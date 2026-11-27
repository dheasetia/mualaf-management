<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Checklists;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\International\Enums\ChecklistStatus;
use App\Domain\International\Enums\ItemStatus;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Filament\Center\Resources\Checklists\Pages\ListChecklists;
use App\Filament\Center\Resources\Checklists\Pages\ViewChecklist;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Pre-departure checklists (PRD §33).
 */
final class ChecklistResource extends Resource
{
    protected static ?string $model = PreDepartureChecklist::class;

    protected static ?string $slug = 'pre-departure';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('international.checklists.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('international.checklists.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('international.navigation_group');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('followups.view') ?? false;
    }

    public static function convertName(int $convertId): string
    {
        $user = auth()->user();
        $convert = Convert::query()->find($convertId);

        return $user instanceof User && $convert instanceof Convert ? app(ConvertAccess::class)->displayName($user, $convert) : '—';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('international.checklists.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(3)->schema([
                    TextEntry::make('convert')->label(__('international.fields.convert'))->state(fn (PreDepartureChecklist $record): string => self::convertName($record->convert_id)),
                    TextEntry::make('status')->label(__('international.fields.status'))->badge()->formatStateUsing(fn (ChecklistStatus $state): string => $state->label()),
                    TextEntry::make('expected_departure_on')->label(__('international.fields.expected_departure_on'))->date('Y-m-d')->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['items as pending_count' => fn ($items) => $items->where('status', ItemStatus::Pending)]))
            ->columns([
                TextColumn::make('convert')->label(__('international.fields.convert'))->state(fn (PreDepartureChecklist $record): string => self::convertName($record->convert_id)),
                TextColumn::make('expected_departure_on')->label(__('international.fields.expected_departure_on'))->date('Y-m-d')->placeholder('—'),
                TextColumn::make('pending_count')->label(__('international.item_statuses.pending')),
                TextColumn::make('status')->label(__('international.fields.status'))->badge()->formatStateUsing(fn (ChecklistStatus $state): string => $state->label()),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChecklists::route('/'),
            'view' => ViewChecklist::route('/{record}'),
        ];
    }
}
