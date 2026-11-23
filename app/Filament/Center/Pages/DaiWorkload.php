<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Services\DaiWorkload as DaiWorkloadQuery;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Head of Dawah view of how prospects, converts and supervision are spread
 * over the office's active and on-leave da'i (dais.view_workload).
 */
final class DaiWorkload extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewWorkload', Dai::class) ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('dai.workload.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public function getTitle(): string
    {
        return __('dai.workload.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(app(DaiWorkloadQuery::class)->query())
            ->defaultSort('primary_prospects_count', 'desc')
            ->columns([
                TextColumn::make('full_name')->label(__('dai.fields.full_name'))->searchable()->sortable(),
                TextColumn::make('status')->label(__('dai.fields.status'))->badge()
                    ->formatStateUsing(fn (DaiStatus $state): string => $state->label()),
                TextColumn::make('primary_prospects_count')->label(__('dai.workload.primary_prospects'))->numeric()->sortable(),
                TextColumn::make('primary_converts_count')->label(__('dai.workload.primary_converts'))->numeric()->sortable(),
                TextColumn::make('other_assignments_count')->label(__('dai.workload.other_assignments'))->numeric()->sortable(),
                TextColumn::make('supervisees_count')->label(__('dai.workload.supervisees'))->numeric()->sortable(),
            ]);
    }
}
