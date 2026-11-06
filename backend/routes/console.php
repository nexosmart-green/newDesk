<?php

use Illuminate\Support\Facades\Schedule;

// Run by `schedule:work` under Supervisord (`php artisan supervisor:sync`), never crontab (RNF-11).

// RF-05.06 / RF-05.10: budget and forgotten-timer cuts. Granularity = max over-consumption (R-1).
Schedule::command('timers:cut')->everyMinute()->withoutOverlapping(5)->onOneServer();

// Invariants 2–3: caches vs ledgers.
Schedule::command('hours:reconcile')->dailyAt('03:15')->onOneServer();
