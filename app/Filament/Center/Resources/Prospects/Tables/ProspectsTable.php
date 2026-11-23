<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Prospects\Tables;

use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Prospects\Enums\ProspectSource;
use App\Domain\Prospects\Enums\ProspectStatus;
use App\Domain\Prospects\Models\Prospect;
use App\Support\Text\NameSearchKey;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ProspectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('next_follow_up_at')
            ->columns([
                TextColumn::make('full_name')->label(__('prospects.fields.full_name'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('name_search_key', 'like', '%'.addcslashes(NameSearchKey::make($search), '%_\\').'%')),
                TextColumn::make('status')->label(__('prospects.fields.status'))->badge()
                    ->formatStateUsing(fn (ProspectStatus $state): string => $state->label())
                    ->color(fn (ProspectStatus $state): string => $state->color()),
                TextColumn::make('primary_dai_id')->label(__('prospects.fields.primary_dai'))
                    ->formatStateUsing(fn (int $state): string => self::daiNames()[$state] ?? '—'),
                TextColumn::make('source_type')->label(__('prospects.fields.source_type'))
                    ->formatStateUsing(fn (ProspectSource $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_contact_at')->label(__('prospects.fields.last_contact_at'))
                    ->since(timezone: (string) config('app.display_timezone'))->sortable()->placeholder('—'),
                TextColumn::make('next_follow_up_at')->label(__('prospects.fields.next_follow_up_at'))
                    ->dateTime('Y-m-d H:i', timezone: (string) config('app.display_timezone'))->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('prospects.fields.status'))->multiple()
                    ->options(collect(ProspectStatus::cases())->mapWithKeys(fn (ProspectStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('primary_dai_id')->label(__('prospects.fields.primary_dai'))
                    ->options(fn (): array => app(DaiDirectory::class)->activeOptions())
                    ->visible(fn (): bool => auth()->user()?->hasPermission('prospects.view') ?? false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    /**
     * Names of the office's da'i, loaded once per request (once() is flushed between requests and tests) (the Dai module owns the table).
     *
     * @return array<int, string>
     */
    private static function daiNames(): array
    {
        return once(function (): array {
            $ids = array_values(array_map(intval(...), Prospect::query()->distinct()->pluck('primary_dai_id')->all()));

            return app(DaiDirectory::class)->names($ids);
        });
    }
}
