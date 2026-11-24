<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ImportBatches\RelationManagers;

use App\Domain\Registration\Enums\ImportRowStatus;
use App\Domain\Registration\Models\ImportRow;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The rows of an import with their result; the name is decrypted for the
 * importer, who may see the office's converts anyway.
 */
final class RowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('imports.fields.total_rows');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('row_number')
            ->poll('10s')
            ->columns([
                TextColumn::make('row_number')->label(__('imports.fields.row_number')),
                TextColumn::make('name')->label(__('imports.fields.name'))
                    ->state(fn (ImportRow $record): string => (string) $record->cell('full_name')),
                TextColumn::make('status')->label(__('imports.fields.status'))->badge()
                    ->formatStateUsing(fn (ImportRowStatus $state): string => $state->label())
                    ->color(fn (ImportRowStatus $state): string => $state->color()),
                TextColumn::make('error_message')->label(__('imports.fields.error_message'))->wrap()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('imports.fields.status'))
                    ->options(collect(ImportRowStatus::cases())->mapWithKeys(fn (ImportRowStatus $status): array => [$status->value => $status->label()])->all()),
            ]);
    }
}
