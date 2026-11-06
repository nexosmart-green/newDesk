<?php

use App\Domain\StatusPage\Actions\CheckComponents;
use App\Domain\StatusPage\Actions\CheckDomains;
use App\Domain\StatusPage\Actions\MeasurePageSpeed;
use App\Domain\Support\Actions\PurgeReports;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RF-13.03/05: hourly metric rollups and retention (raw 7 days, hourly 1 year, site history 30 days).
Schedule::command('monitoring:retention')->hourly()->withoutOverlapping();

// RF-13.06 status page (monitoreo portal.Service, PageSpeed): long runs go to the background so they
// never hold the rest of the minute's schedule.
Artisan::command('status-page:check', fn (CheckComponents $check) => $check->all())
    ->purpose('Check every status page component once (http/health probes, pm2/supervisor/timer from snapshots)');
Artisan::command('status-page:domains', fn (CheckDomains $check) => $check())
    ->purpose('Look up domain expiry by RDAP (once a day per project) and mail the 30/7-day notices');
Artisan::command('status-page:pagespeed', fn (MeasurePageSpeed $measure) => $measure->due())
    ->purpose('Measure with PageSpeed Insights the web components due (weekly, at most 6 per pass)');

// RF-13.08 retention (D-041, D-049, D-093): report images 180 days, reports 1 year, orphan files.
Artisan::command('support:retention', function (PurgeReports $purge) {
    foreach ($purge(now()) as $kind => $count) {
        $this->line("{$kind}: {$count} borrados");
    }
})->purpose('Purge report images after 180 days, reports after a year and report files no row names');

Schedule::command('status-page:check')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('support:retention')->hourly()->withoutOverlapping();
Schedule::command('status-page:domains')->hourly()->withoutOverlapping()->runInBackground();
Schedule::command('status-page:pagespeed')->hourly()->withoutOverlapping()->runInBackground();
