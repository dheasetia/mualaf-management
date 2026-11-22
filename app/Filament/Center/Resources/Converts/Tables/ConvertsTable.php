<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\Tables;

use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Dai\Services\DaiDirectory;
use App\Filament\Center\Resources\Converts\ConvertResource;
use App\Models\User;
use App\Support\Text\NameSearchKey;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ConvertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Convert $record): ?string => auth()->user()?->can('view', $record)
                ? ConvertResource::getUrl('view', ['record' => $record]) : null)
            ->columns([
                // What the viewer may see of the name: full, minimal or an alias.
                TextColumn::make('display_name')->label(__('converts.fields.display_name'))
                    ->state(fn (Convert $record): string => self::displayName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('disclosure_risk_level', '!=', DisclosureLevel::HighConfidentiality->value)
                        ->where('name_search_key', 'like', '%'.addcslashes(NameSearchKey::make($search), '%_\\').'%')),
                TextColumn::make('status')->label(__('converts.fields.status'))->badge()
                    ->formatStateUsing(fn (ConvertStatus $state): string => $state->label())
                    ->color(fn (ConvertStatus $state): string => $state->color()),
                TextColumn::make('disclosure_risk_level')->label(__('converts.fields.disclosure_risk_level'))->badge()
                    ->formatStateUsing(fn (DisclosureLevel $state): string => $state->label())
                    ->color(fn (DisclosureLevel $state): string => $state->color()),
                TextColumn::make('primary_dai_id')->label(__('converts.fields.primary_dai'))
                    ->formatStateUsing(fn (int $state): string => self::daiNames()[$state] ?? '—'),
                TextColumn::make('created_at')->label(__('converts.fields.changed_at'))
                    ->date('Y-m-d', timezone: (string) config('app.display_timezone'))->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('converts.fields.status'))
                    ->options(collect(ConvertStatus::cases())->mapWithKeys(fn (ConvertStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('disclosure_risk_level')->label(__('converts.fields.disclosure_risk_level'))
                    ->options(collect(DisclosureLevel::cases())->mapWithKeys(fn (DisclosureLevel $level): array => [$level->value => $level->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    private static function displayName(Convert $record): string
    {
        $user = auth()->user();

        return $user instanceof User ? app(ConvertAccess::class)->displayName($user, $record) : $record->alias();
    }

    /** @return array<int, string> */
    private static function daiNames(): array
    {
        return once(fn (): array => app(DaiDirectory::class)->names(
            array_values(array_map(intval(...), Convert::query()->distinct()->pluck('primary_dai_id')->all())),
        ));
    }
}
