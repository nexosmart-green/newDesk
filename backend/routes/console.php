<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RF-13.03: hourly metric rollups and retention (raw 7 days, hourly 1 year).
Schedule::command('monitoring:retention')->hourly()->withoutOverlapping();
