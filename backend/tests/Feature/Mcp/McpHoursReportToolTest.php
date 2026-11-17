<?php

use App\Domain\Identity\DefaultRoles;
use Illuminate\Support\Carbon;

require_once __DIR__.'/McpTestHelpers.php';
require_once __DIR__.'/../Tasks/ParityHelpers.php';

/** Two clients, an internal project without client, two people; plus entries the report must ignore. */
function hoursFixture(): array
{
    $admin = adminUser();
    $ana = userWithRole($admin);
    $beto = userWithRole($admin);
    ['task' => $bhn] = bagProject($admin, [$ana, $beto], 100 * 3600, 'bhn');
    ['task' => $acm] = bagProject($admin, [$ana], 100 * 3600, 'acm');
    ['project' => $internal, 'task' => $int] = bagProject($admin, [$beto], 0, 'int', 'internal');
    $internal->forceFill(['client_id' => null])->save();
    $day = Carbon::parse('2026-11-10 13:00', 'UTC');

    logEntry($ana, $bhn, $day, 2 * 3600);
    logEntry($beto, $bhn, $day, 3600);
    logEntry($ana, $acm, $day, 2700);
    logEntry($beto, $int, $day, 5400);
    logEntry($ana, $bhn, $day->copy()->addHours(3), null);              // running: not consumed yet
    logEntry($ana, $bhn, $day->copy()->subMonth(), 7200);                // outside the period
    logEntry($ana, $acm, $day, 600)->delete();                           // deleted

    return compact('admin', 'ana', 'beto');
}

it('reports the hours of the whole company by project, client and person with the same totals as the web (RF-14.07)', function () {
    ['admin' => $admin, 'ana' => $ana, 'beto' => $beto] = hoursFixture();
    $token = mcpToken($admin);
    $range = ['from' => '2026-11-01', 'to' => '2026-11-15'];

    $byProject = mcpResult(mcpCall($token, 'hours_report', $range))['json'];
    $byClient = mcpResult(mcpCall($token, 'hours_report', [...$range, 'group_by' => 'client']))['json'];
    $byPerson = mcpResult(mcpCall($token, 'hours_report', [...$range, 'group_by' => 'person']))['json'];

    $total = 2 * 3600 + 3600 + 2700 + 5400;
    expect([$byProject['total_seconds'], $byClient['total_seconds'], $byPerson['total_seconds']])->toBe([$total, $total, $total])
        ->and($byPerson['total'])->toBe('5h 15m')
        ->and($byPerson['rows'][0])->toMatchArray(['user_id' => $ana->id, 'seconds' => 9900, 'time' => '2h 45m'])
        ->and($byPerson['rows'][1]['by_project'][0])->toMatchArray(['prefix' => 'int', 'seconds' => 5400, 'time' => '1h 30m'])
        ->and($byPerson['rows'][1])->toMatchArray(['user_id' => $beto->id, 'seconds' => 9000])
        ->and(collect($byClient['rows'])->firstWhere('client', null))->toMatchArray(['seconds' => 5400, 'projects' => 1])
        ->and(collect($byProject['rows'])->firstWhere('project.prefix', 'bhn'))->toMatchArray(['seconds' => 3 * 3600, 'allocated' => '100h 00m']);

    // Same query as the web report for the same range.
    $query = http_build_query($range);
    $web = fn (string $path, string $key) => $this->actingAs($admin)->getJson("/api/v1/reports/hours{$path}?{$query}")->assertOk()->json("data.{$key}");
    expect(array_sum(array_column($web('', 'projects'), 'consumed_in_period_seconds')))->toBe($total)
        ->and($web('/clients', 'clients'))->toBe(array_map(fn ($row) => array_diff_key($row, ['time' => 0]), $byClient['rows']))
        ->and(array_column($web('/people', 'people'), 'seconds'))->toBe(array_column($byPerson['rows'], 'seconds'));
});

it('keeps the company report to projects.manage, with the module switched on, and hides other companies', function () {
    ['admin' => $admin] = hoursFixture();

    expect(mcpResult(mcpCall(mcpToken(userWithRole($admin)), 'hours_report')))
        ->toMatchArray(['error' => true])->text->toContain('Tu rol no tiene acceso a: ');
    $this->actingAs(userWithRole($admin))->getJson('/api/v1/reports/hours/people')->assertForbidden();

    $other = adminUser('otra@example.com');
    expect(mcpResult(mcpCall(mcpToken($other), 'hours_report', ['from' => '2026-11-01', 'to' => '2026-11-15', 'group_by' => 'person']))['json'])
        ->toMatchArray(['total_seconds' => 0, 'rows' => []]);
    expect(mcpResult(mcpCall(mcpToken(userWithRole($admin, DefaultRoles::MANAGER)), 'hours_report', ['group_by' => 'client']))['error'])->toBeFalse();
});

it('rejects a backwards range with the same message as the web', function () {
    $admin = adminUser();
    $web = $this->actingAs($admin)->getJson('/api/v1/reports/hours/clients?from=2026-11-10&to=2026-11-01')->assertUnprocessable()->json('message');

    expect(mcpResult(mcpCall(mcpToken($admin), 'hours_report', ['group_by' => 'client', 'from' => '2026-11-10', 'to' => '2026-11-01'])))
        ->toMatchArray(['error' => true, 'text' => $web]);
});
