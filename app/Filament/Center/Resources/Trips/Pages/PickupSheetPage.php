<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Trips\Pages;

use App\Domain\Transport\Models\TransportTrip;
use App\Domain\Transport\Services\PickupSheet;
use App\Filament\Center\Resources\Trips\TripResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Printable pickup sheet for the driver (no religion or shahadah data).
 */
final class PickupSheetPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TripResource::class;

    protected string $view = 'filament.center.transport.pickup-sheet';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return __('transport.sheet.title');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $trip = $this->getRecord();

        return ['sheet' => $trip instanceof TransportTrip ? app(PickupSheet::class)->for($trip) : null];
    }
}
