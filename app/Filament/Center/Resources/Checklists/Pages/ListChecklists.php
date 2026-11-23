<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Checklists\Pages;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\International\Actions\Checklists;
use App\Domain\Platform\Models\Country;
use App\Filament\Center\Resources\Checklists\ChecklistResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

final class ListChecklists extends ListRecords
{
    use RunsDomainActions;

    protected static string $resource = ChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openChecklist')->label(__('international.actions.open_checklist'))
                ->visible(fn (): bool => auth()->user()?->hasPermission('followups.create') ?? false)
                ->schema([
                    Select::make('convert_id')->label(__('international.fields.convert'))->required()->searchable()
                        ->options(fn (): array => ($user = auth()->user()) instanceof User ? app(ConvertAccess::class)->visible(Convert::query(), $user)->orderBy('full_name')->limit(500)->get()
                            ->mapWithKeys(fn (Convert $convert): array => [$convert->id => app(ConvertAccess::class)->displayName($user, $convert)])->all() : []),
                    Select::make('destination_country_id')->label(__('international.fields.destination_country'))->searchable()
                        ->options(fn (): array => Country::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
                    DatePicker::make('expected_departure_on')->label(__('international.fields.expected_departure_on')),
                ])
                ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(Checklists::class)->open(
                    Convert::query()->findOrFail((int) $data['convert_id']),
                    blank($data['destination_country_id'] ?? null) ? null : (int) $data['destination_country_id'],
                    blank($data['expected_departure_on'] ?? null) ? null : CarbonImmutable::parse((string) $data['expected_departure_on']),
                    $user,
                ), 'international.notifications.saved')),
        ];
    }
}
