<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\MaterialDistributions;

use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Dawah\Models\DawahMaterialDistribution;
use App\Filament\Center\Resources\MaterialDistributions\Pages\ListMaterialDistributions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only log of material handed out in this office; recorded from the
 * materials screen.
 */
final class MaterialDistributionResource extends Resource
{
    protected static ?string $model = DawahMaterialDistribution::class;

    protected static ?string $slug = 'material-distributions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('dawah.distributions.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dawah.distributions.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dawah.navigation_group');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('distributed_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('material'))
            ->columns([
                TextColumn::make('distributed_at')->label(__('dawah.material_fields.distributed_at'))->dateTime('Y-m-d H:i', timezone: (string) config('app.display_timezone'))->sortable(),
                TextColumn::make('material.title')->label(__('dawah.material_fields.material'))->limit(40),
                TextColumn::make('quantity')->label(__('dawah.material_fields.quantity')),
                TextColumn::make('dai')->label(__('dawah.material_fields.dai'))
                    ->state(fn (DawahMaterialDistribution $record): string => app(DaiDirectory::class)->names([$record->dai_id])[$record->dai_id] ?? '—'),
                TextColumn::make('notes')->label(__('dawah.material_fields.notes'))->limit(40)->placeholder('—'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterialDistributions::route('/'),
        ];
    }
}
