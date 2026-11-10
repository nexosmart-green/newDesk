<?php

use App\Domain\Monitoring\Actions\SendAlertDigest;
use App\Domain\Monitoring\Actions\SendHeartbeat;
use App\Domain\Monitoring\Actions\WatchProjectErrors;
use App\Domain\StatusPage\Actions\CheckComponents;
use App\Domain\StatusPage\Actions\CheckDomains;
use App\Domain\StatusPage\Actions\MeasurePageSpeed;
use App\Domain\Support\Actions\PurgeReports;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Run by `schedule:work` under Supervisord (`php artisan supervisor:sync`), never crontab (RNF-11).

// RF-05.06 / RF-05.10: budget and forgotten-timer cuts. Granularity = max over-consumption (R-1).
Schedule::command('timers:cut')->everyMinute()->withoutOverlapping(5)->onOneServer();

// RF-09.03: daily summary at 08:00 local time of each company.
Schedule::command('tasks:notify-due')->hourly()->onOneServer();

// Invariants 2–3: caches vs ledgers.
Schedule::command('hours:reconcile')->dailyAt('03:15')->onOneServer();

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

// RF-13.09 notices, all silent while MONITORING_NOTIFY is off (CA-13.20).
Artisan::command('monitoring:digest', fn (SendAlertDigest $digest) => $digest())
    ->purpose('Mail and post to #resumen the alert digest since the previous 9/18 h cut');
Artisan::command('monitoring:errors-watch', fn (WatchProjectErrors $watch) => $watch())
    ->purpose('Post to each project channel the new ERROR entries of its /api/errors');
Artisan::command('monitoring:heartbeat', fn (SendHeartbeat $heartbeat) => $heartbeat())
    ->purpose('Ping HEALTHCHECKS_URL while the database and the poller are healthy');

Schedule::command('monitoring:digest')->twiceDaily(...SendAlertDigest::HOURS)->timezone(SendAlertDigest::TIMEZONE);
Schedule::command('monitoring:errors-watch')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
Schedule::command('monitoring:heartbeat')->everyMinute()->runInBackground();
Schedule::command('status-page:check')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('support:retention')->hourly()->withoutOverlapping();
Schedule::command('status-page:domains')->hourly()->withoutOverlapping()->runInBackground();
Schedule::command('status-page:pagespeed')->hourly()->withoutOverlapping()->runInBackground();
