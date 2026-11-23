<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Dais\Tables;

use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Enums\EmploymentStatus;
use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Models\Language;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class DaisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['supervisor', 'languages']))
            ->defaultSort('full_name')
            ->columns([
                TextColumn::make('employee_number')->label(__('dai.fields.employee_number'))->searchable()->sortable(),
                TextColumn::make('full_name')->label(__('dai.fields.full_name'))->searchable()->sortable()
                    ->description(fn (Dai $record): ?string => $record->full_name_ar),
                TextColumn::make('status')->label(__('dai.fields.status'))->badge()
                    ->formatStateUsing(fn (DaiStatus $state): string => $state->label())
                    ->color(fn (DaiStatus $state): string => match ($state) {
                        DaiStatus::Active => 'success',
                        DaiStatus::OnLeave => 'warning',
                        DaiStatus::Inactive => 'gray',
                    }),
                TextColumn::make('employment_status')->label(__('dai.fields.employment_status'))
                    ->formatStateUsing(fn (EmploymentStatus $state): string => $state->label()),
                TextColumn::make('supervisor.full_name')->label(__('dai.fields.supervisor'))->placeholder('—'),
                TextColumn::make('languages.name_ar')->label(__('dai.fields.languages'))->badge()->limitList(3),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('dai.fields.status'))
                    ->options(collect(DaiStatus::cases())->mapWithKeys(fn (DaiStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('language')->label(__('dai.fields.language'))
                    ->options(fn (): array => Language::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas('languages', fn (Builder $languages) => $languages->whereKey($data['value'])),
                    ))
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
