<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ImportBatches;

use App\Domain\Registration\Enums\ImportBatchStatus;
use App\Domain\Registration\Models\ImportBatch;
use App\Filament\Center\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Center\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Filament\Center\Resources\ImportBatches\RelationManagers\RowsRelationManager;
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
 * Imports of historical converts for the office (11 §11.5).
 */
final class ImportBatchResource extends Resource
{
    protected static ?string $model = ImportBatch::class;

    protected static ?string $slug = 'convert-imports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 24;

    public static function getModelLabel(): string
    {
        return __('imports.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('imports.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public static function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');

        return $table
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('created_at')->label(__('imports.fields.created_at'))->dateTime('Y-m-d H:i', $timezone),
                TextColumn::make('original_filename')->label(__('imports.fields.original_filename')),
                TextColumn::make('status')->label(__('imports.fields.status'))->badge()
                    ->formatStateUsing(fn (ImportBatchStatus $state): string => $state->label())
                    ->color(fn (ImportBatchStatus $state): string => $state->color()),
                TextColumn::make('total_rows')->label(__('imports.fields.total_rows')),
                TextColumn::make('success_rows')->label(__('imports.fields.success_rows')),
                TextColumn::make('duplicate_rows')->label(__('imports.fields.duplicate_rows')),
                TextColumn::make('failed_rows')->label(__('imports.fields.failed_rows')),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $timezone = (string) config('app.display_timezone');

        return $schema->components([
            Section::make(__('imports.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(3)->schema([
                    TextEntry::make('original_filename')->label(__('imports.fields.original_filename')),
                    TextEntry::make('status')->label(__('imports.fields.status'))->badge()
                        ->formatStateUsing(fn (ImportBatchStatus $state): string => $state->label())
                        ->color(fn (ImportBatchStatus $state): string => $state->color()),
                    TextEntry::make('uploader.name')->label(__('imports.fields.uploaded_by')),
                    TextEntry::make('created_at')->label(__('imports.fields.created_at'))->dateTime('Y-m-d H:i', $timezone),
                    TextEntry::make('completed_at')->label(__('imports.fields.completed_at'))->dateTime('Y-m-d H:i', $timezone)->placeholder('—'),
                ]),
                Fieldset::make(__('forms.result'))->columns(4)->schema([
                    TextEntry::make('total_rows')->label(__('imports.fields.total_rows')),
                    TextEntry::make('success_rows')->label(__('imports.fields.success_rows')),
                    TextEntry::make('duplicate_rows')->label(__('imports.fields.duplicate_rows')),
                    TextEntry::make('failed_rows')->label(__('imports.fields.failed_rows')),
                ]),
                Fieldset::make(__('forms.details'))->columns(1)->schema([
                    TextEntry::make('error_summary')->label(__('imports.fields.error_summary'))->placeholder('—')->columnSpan(3),
                ]),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [RowsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportBatches::route('/'),
            'view' => ViewImportBatch::route('/{record}'),
        ];
    }
}
