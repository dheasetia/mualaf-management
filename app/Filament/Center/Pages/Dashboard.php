<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Domain\Prospects\Models\Prospect;
use App\Filament\Center\Resources\FollowUpSchedules\FollowUpScheduleResource;
use App\Filament\Center\Resources\Prospects\ProspectResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * The office home page: quick actions for what this user may do, the office
 * figures and this user's own work for today (Center/Widgets).
 */
final class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return __('dashboard.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registerShahadah')
                ->label(__('dashboard.actions.register_shahadah'))
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => RegisterShahadah::getUrl())
                ->visible(fn (): bool => RegisterShahadah::canAccess()),
            Action::make('newProspect')
                ->label(__('dashboard.actions.new_prospect'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->url(fn (): string => ProspectResource::getUrl('create'))
                ->visible(fn (): bool => auth()->user()?->can('create', Prospect::class) ?? false),
            Action::make('followUps')
                ->label(__('dashboard.actions.follow_ups'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(fn (): string => FollowUpScheduleResource::getUrl())
                ->visible(fn (): bool => FollowUpScheduleResource::canViewAny()),
        ];
    }
}
