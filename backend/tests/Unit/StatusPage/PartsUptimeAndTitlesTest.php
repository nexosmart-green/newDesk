<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Queries\StatusPage;
use App\Domain\StatusPage\Queries\Vulnerabilities;
use App\Domain\StatusPage\Status\Parts;
use App\Domain\StatusPage\Status\Uptime;
use Carbon\CarbonImmutable;
use Tests\Support\StatusPageFixtures;

const BUENOS_AIRES = 'America/Argentina/Buenos_Aires';

// portal/parts_test.go
it('joins part labels the Spanish way and normalizes them', function () {
    expect(Parts::label([]))->toBe('')
        ->and(Parts::label(['cms']))->toBe('CMS')
        ->and(Parts::label(['cms', 'app']))->toBe('App y CMS')
        ->and(Parts::label(['cms', 'web', 'app']))->toBe('Web, App y CMS')
        ->and(Parts::normalize(['cms', 'web', 'cms']))->toBe(['web', 'cms'])
        ->and(Parts::normalize(['web', 'api']))->toBeNull()
        ->and(Parts::normalize([]))->toBe([]);
});

it('defaults a component to the parts its kind suggests (D-045)', function (string $kind, ?string $url, array $existing, array $want) {
    $siblings = array_map(fn (array $parts) => StatusPageFixtures::component(['parts' => $parts]), $existing);

    expect(Parts::default($kind, $url, $siblings))->toBe($want);
})->with([
    [Component::HTTP, 'https://playinn.com/', [], ['web']],
    [Component::HTTP, 'https://cms.playinn.com/', [], ['cms']],
    [Component::HTTP, 'https://app.clubolimpo.com.ar', [], ['app']],
    [Component::HEALTH, 'https://api.playinn.com/health', [['web'], ['cms']], ['web', 'cms']],
    [Component::SUPERVISOR, null, [['web'], ['cms']], ['web', 'cms']],
    [Component::PM2, null, [], ['web']],
    [Component::TIMER, null, [['web'], ['cms']], []],
]);

it('places a web URL by its host, else by its folder', function (string $host, string $folder, string $part) {
    expect(Parts::place($host, $folder))->toBe($part);
})->with([
    ['playinn.com', 'playinn', 'web'], ['cms.playinn.com', 'playinn', 'cms'], ['admin.medplace.com', 'medplace', 'cms'],
    ['app.clubolimpo.com.ar', 'olimpo', 'app'], ['medplace.com', 'medplace-cms', 'cms'], ['clubolimpo.com.ar', 'olimpo_app', 'app'],
    ['apple-store.com', 'tienda', 'app'], ['www.tooki.com.ar', 'tooki-web', 'web'],
]);

it('shows each part as the worst of the components that affect it', function () {
    $since = CarbonImmutable::parse('2026-10-07 14:00:00', 'UTC');
    $parts = Parts::states([
        StatusPageFixtures::component(['label' => 'Sitio', 'parts' => ['web'], 'status' => 'operational', 'phrase' => 'Funciona normalmente']),
        StatusPageFixtures::component(['label' => 'App', 'parts' => ['app'], 'status' => 'operational', 'phrase' => 'Funciona normalmente']),
        StatusPageFixtures::component(['label' => 'API', 'parts' => ['app', 'cms'], 'status' => 'down', 'phrase' => 'No responde', 'status_since' => $since]),
        StatusPageFixtures::component(['label' => 'Backups', 'status' => 'degraded', 'phrase' => 'x']),
    ]);

    expect(array_column($parts, 'label'))->toBe(['Web', 'App', 'CMS'])
        ->and($parts[2]['status'])->toBe('down')
        ->and([$parts[1]['status'], $parts[1]['phrase']])->toBe(['down', 'No responde'])
        ->and($parts[1]['since']->equalTo($since))->toBeTrue();
});

// portal/uptime_test.go TestComputeUptime
it('builds 90 daily bars and counts only down time, once (D-039)', function () {
    $at = fn (int $day, int $hour, int $minute) => CarbonImmutable::create(2026, 10, $day, $hour, $minute, 0, BUENOS_AIRES)->utc();
    $now = $at(7, 12, 0);
    $start = $at(1, 0, 0);
    $periods = [
        StatusPageFixtures::period(1, 'down', $at(3, 14, 2), $at(3, 14, 9)),
        StatusPageFixtures::period(2, 'down', $at(3, 14, 5), $at(3, 14, 7)),
        StatusPageFixtures::period(2, 'degraded', $at(5, 10, 0), $at(5, 11, 0)),
        StatusPageFixtures::period(1, 'down', $at(5, 10, 30), $at(5, 10, 40)),
        StatusPageFixtures::period(3, 'degraded', $at(7, 11, 0)),
    ];

    $uptime = Uptime::compute($periods, $start, $now, BUENOS_AIRES);
    $days = array_column($uptime['days'], null, 'date');
    expect($uptime['days'])->toHaveCount(90)
        ->and(end($uptime['days'])['date'])->toBe('2026-10-07')
        ->and($days['2026-09-30']['status'])->toBe('nodata')
        ->and($days['2026-10-01']['status'])->toBe('operational')
        ->and($days['2026-10-03'])->toMatchArray(['status' => 'down', 'down_minutes' => 7, 'degraded_minutes' => 0])
        ->and($days['2026-10-05'])->toMatchArray(['status' => 'down', 'down_minutes' => 10, 'degraded_minutes' => 50])
        ->and($days['2026-10-07'])->toMatchArray(['status' => 'degraded', 'down_minutes' => 0, 'degraded_minutes' => 60]);

    $window = ($now->getTimestamp() - $start->getTimestamp()) / 60;
    $want = floor(100 * (1 - 17 / $window) * 100) / 100;
    expect($uptime['month_pct'])->toBe($want)->and($uptime['range_pct'])->toBe($want);

    $empty = Uptime::compute([], $now->addHour(), $now, BUENOS_AIRES);
    expect($empty['month_pct'])->toBeNull()->and(end($empty['days'])['status'])->toBe('nodata');
});

// TestStatusWithoutComponentsHasNoUptime
it('measures nothing for a project without components', function () {
    $now = CarbonImmutable::parse('2026-10-07 15:00:00', 'UTC');
    $project = (new Project)->forceFill(['slug' => 'nuevo', 'name' => 'Nuevo'])->setRelation('client', (new Client)->forceFill(['name' => 'X']));

    $view = StatusPage::build($project, collect(), collect(), null, $now);
    expect($view['uptime']['month_pct'])->toBeNull()->and($view['uptime']['range_pct'])->toBeNull()
        ->and(end($view['uptime']['days'])['status'])->toBe('nodata');
});

// portal/history_test.go TestIncidentTitle
it('titles an incident in the client words and Buenos Aires time (D-042)', function (array $component, array $start, ?array $end, string $title) {
    $at = fn (array $time) => CarbonImmutable::create(2026, 10, $time[0], $time[1], $time[2], 0, BUENOS_AIRES)->utc();

    expect(StatusPage::incidentTitle(StatusPageFixtures::component($component), $at($start), $end === null ? null : $at($end)))->toBe($title);
})->with([
    [['label' => 'API', 'kind' => 'health', 'parts' => ['app', 'cms']], [7, 14, 2], [7, 14, 9], 'App y CMS no respondieron el 7/10 entre las 14:02 y las 14:09.'],
    [['label' => 'API', 'kind' => 'health', 'parts' => ['app', 'cms']], [6, 23, 50], [7, 0, 10], 'App y CMS no respondieron desde el 6/10 a las 23:50 hasta el 7/10 a las 00:10.'],
    [['label' => 'API', 'kind' => 'health', 'parts' => ['app', 'cms']], [7, 14, 2], null, 'App y CMS no responden desde el 7/10 a las 14:02.'],
    [['label' => 'Sitio', 'kind' => 'http', 'parts' => ['web']], [7, 14, 2], null, 'Web no responde desde el 7/10 a las 14:02.'],
    [['label' => 'Workers', 'kind' => 'supervisor', 'parts' => ['app']], [7, 3, 0], [7, 3, 5], 'App funcionó con problemas el 7/10 entre las 03:00 y las 03:05.'],
    [['label' => 'Workers', 'kind' => 'supervisor'], [7, 3, 0], null, 'Workers funciona con problemas desde el 7/10 a las 03:00.'],
]);

// portal/vulnerabilities_test.go
it('gives each audited folder to the deepest linked folder of its server (D-053)', function () {
    $dir = fn (string $path) => ['path' => $path, 'manager' => 'npm', 'counts' => ['critical' => 0, 'high' => 1, 'moderate' => 0, 'low' => 0]];
    $scans = [
        1 => ['items' => [$dir('/var/www/reaquila.com/crm'), $dir('/var/www/reaquila.com'), $dir('/var/www/reaquila.com-old'), $dir('/var/www/otro')]],
        2 => ['items' => [$dir('/var/www/reaquila.com/crm')]],
    ];
    $folders = [
        ['project_id' => 10, 'server_id' => 1, 'path' => '/var/www/reaquila.com'],
        ['project_id' => 20, 'server_id' => 1, 'path' => '/var/www/reaquila.com/crm'],
    ];

    $got = Vulnerabilities::assign($scans, $folders, [1 => 'Servidor 5', 2 => 'Servidor 8']);
    expect($got->byProject[20])->toHaveCount(1)
        ->and($got->byProject[20][0])->toMatchArray(['path' => '/var/www/reaquila.com/crm', 'server_name' => 'Servidor 5'])
        ->and(array_column($got->byProject[10], 'path'))->toBe(['/var/www/reaquila.com'])
        ->and($got->unassigned)->toHaveCount(3)
        ->and($got->counts(10)['high'])->toBe(1);
});
