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

// PRD §31: suggest follow-ups (never statuses) for converts nobody has contacted for a while.
Schedule::command('followups:flag-quiet')->dailyAt('03:00')->withoutOverlapping()->onOneServer();

// 11 §11.8 and §11.5: nightly statistics, and export files removed after they expire.
Schedule::command('reports:aggregate')->dailyAt('01:30')->withoutOverlapping()->onOneServer();
Schedule::command('exports:purge')->hourly()->withoutOverlapping()->onOneServer();
