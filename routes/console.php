<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 12-transactions-state-machines §12.3: unanswered convert transfers go to Central.
Schedule::command('transfers:escalate')->hourly()->withoutOverlapping()->onOneServer();
