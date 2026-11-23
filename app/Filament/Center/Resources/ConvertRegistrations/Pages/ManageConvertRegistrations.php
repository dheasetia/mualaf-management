<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ConvertRegistrations\Pages;

use App\Filament\Center\Pages\RegisterShahadah;
use App\Filament\Center\Resources\ConvertRegistrations\ConvertRegistrationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

final class ManageConvertRegistrations extends ManageRecords
{
    protected static string $resource = ConvertRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('register')->label(__('registration.actions.register_shahadah'))
                ->url(fn (): string => RegisterShahadah::getUrl())
                ->visible(fn (): bool => RegisterShahadah::canAccess()),
        ];
    }
}
