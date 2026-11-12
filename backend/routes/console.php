<?php

use Illuminate\Support\Facades\Schedule;

// Run by `schedule:work` under Supervisord (`php artisan supervisor:sync`), never crontab (RNF-11).

// RF-05.06 / RF-05.10: budget and forgotten-timer cuts. Granularity = max over-consumption (R-1).
Schedule::command('timers:cut')->everyMinute()->withoutOverlapping(5)->onOneServer();

// RF-09.03: daily summary at 08:00 local time of each company.
Schedule::command('tasks:notify-due')->hourly()->onOneServer();

// Invariants 2–3: caches vs ledgers.
Schedule::command('hours:reconcile')->dailyAt('03:15')->onOneServer();

// RF-06.03: recurring shifts stay generated 8 weeks ahead (idempotent).
Schedule::command('shifts:extend-recurrences')->dailyAt('02:30')->onOneServer();
