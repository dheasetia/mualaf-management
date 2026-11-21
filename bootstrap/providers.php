<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\CenterPanelProvider;
use App\Providers\Filament\CentralPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    CenterPanelProvider::class,
    CentralPanelProvider::class,
    HorizonServiceProvider::class,
];
