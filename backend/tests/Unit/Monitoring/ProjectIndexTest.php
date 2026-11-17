<?php

use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Queries\ProjectIndex;

/** Port of monitoreo view/inventory_test.go, on its testdata fixtures. */
function monitoreoFixture(string $server, string $section): string
{
    return file_get_contents(base_path("tests/Fixtures/monitoreo/servers/{$server}/{$section}.json"));
}

it('flattens every server\'s projects with the domains nginx serves from them, sorted by name', function () {
    $server = fn (int $id, string $name, bool $enabled, string $status) => (new Server)->forceFill(['id' => $id, 'name' => $name, 'enabled' => $enabled, 'status' => $status]);
    $servers = [$server(1, 'reaquila', true, 'online'), $server(2, 'clientes', false, 'online'), $server(3, 'roto', true, 'online'), $server(4, 'sin-datos', true, 'unknown')];
    $projects = [1 => monitoreoFixture('reaquila', 'projects'), 2 => monitoreoFixture('clientes', 'projects'), 3 => '{not json'];
    $sites = [1 => monitoreoFixture('reaquila', 'sites'), 2 => monitoreoFixture('clientes', 'sites')];

    $index = ProjectIndex::build($servers, $projects, $sites);
    $find = fn (string $server, string $name) => collect($index)->first(fn (array $row) => $row['server_name'] === $server && $row['name'] === $name)
        ?? throw new RuntimeException("{$server}/{$name} not indexed");

    $clubolimpo = $find('reaquila', 'clubolimpo.com.ar');
    expect($clubolimpo['domains'])->toBe(['www.clubolimpo.com.ar']) // redirect-only names are not domains of the project
        ->and($clubolimpo['server_status'])->toBe('online')
        ->and($clubolimpo['branch'])->not->toBe('')
        ->and($clubolimpo['subapps'])->not->toBeEmpty()
        ->and($clubolimpo['linked'])->toBeNull();

    // nginx serves playinn/back and playinn/front, subapps of the playinn monorepo.
    $playinn = $find('clientes', 'playinn');
    expect($playinn['domains'])->toBe(['clientes.nexosmart.com.ar'])
        ->and($playinn['server_status'])->toBe('disabled')
        ->and($playinn['server_id'])->toBe(2);
    // Served by the "_" catch-all and by the clientes vhost: only the real name counts.
    expect($find('clientes', 'nothinghere!')['domains'])->toBe(['clientes.nexosmart.com.ar']);

    expect(collect($index)->whereIn('server_id', [3, 4]))->toBeEmpty();
    $names = array_map(fn (array $row) => mb_strtolower($row['name']), $index);
    $sorted = $names;
    sort($sorted, SORT_STRING);
    expect($names)->toBe($sorted);

    // monitoreo omits an empty framework version or branch.
    $bare = ProjectIndex::build([$servers[0]], [1 => '{"items":[{"path":"/var/www/x","name":"x","type":"static"}]}'], []);
    expect($bare[0])->toBe([
        'server_id' => 1, 'server_name' => 'reaquila', 'server_status' => 'online', 'path' => '/var/www/x', 'name' => 'x',
        'type' => 'static', 'domains' => [], 'subapps' => [], 'linked' => null,
    ]);
});
