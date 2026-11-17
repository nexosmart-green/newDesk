<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Shared\Activity\ActivitySource;

require_once __DIR__.'/McpTestHelpers.php';

it('creates, edits only what is sent and inactivates clients like the web, logged with source mcp (RF-14.07)', function () {
    $admin = adminUser();
    $token = mcpToken($admin);

    $created = mcpResult(mcpCall($token, 'create_client', ['legal_name' => 'Acme SA', 'tax_id' => '30-71234567-8', 'email' => 'hola@acme.com']));
    expect($created['error'])->toBeFalse()->and($created['json']['created'])->toMatchArray(['name' => 'Acme SA', 'status' => 'active']);
    $id = $created['json']['created']['id'];

    $updated = mcpResult(mcpCall($token, 'update_client', ['client_id' => $id, 'trade_name' => 'Acme']))['json']['updated'];
    expect($updated)->toMatchArray(['name' => 'Acme', 'legal_name' => 'Acme SA', 'tax_id' => '30-71234567-8', 'email' => 'hola@acme.com']);

    mcpCall($token, 'change_client_status', ['client_id' => $id, 'status' => 'inactive']);
    expect(Client::query()->find($id)->status->value)->toBe('inactive')
        ->and(ActivityLog::query()->where('subject_id', $id)->pluck('event')->all())->toBe(['client.created', 'client.updated', 'client.status_changed'])
        ->and(ActivityLog::query()->where('subject_id', $id)->pluck('source')->unique()->all())->toBe([ActivitySource::Mcp]);

    $listed = mcpResult(mcpCall($token, 'list_clients', ['status' => 'inactive']))['json'];
    expect($listed)->toMatchArray(['total' => 1, 'page' => 1, 'has_more' => false])->and($listed['clients'][0]['id'])->toBe($id);
});

it('rejects the same invalid data with the same message as the web form', function () {
    $admin = adminUser();
    $web = $this->actingAs($admin)->postJson('/api/v1/clients', ['legal_name' => 'Acme SA', 'tax_id' => '123'])
        ->assertUnprocessable()->json('errors.tax_id.0');

    $mcp = mcpResult(mcpCall(mcpToken($admin), 'create_client', ['legal_name' => 'Acme SA', 'tax_id' => '123']));
    expect($mcp['error'])->toBeTrue()->and($mcp['text'])->toContain($web)
        ->and(Client::query()->count())->toBe(0);

    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    expect(mcpResult(mcpCall(mcpToken($admin), 'update_client', ['client_id' => $client->id, 'legal_name' => ''])))->toMatchArray(['error' => true]);
});

it('lets a Gestor list clients but not change them; other companies do not exist for MCP either', function () {
    $admin = adminUser();
    $client = Client::factory()->create(['company_id' => $admin->company_id, 'legal_name' => 'Acme SA']);
    $manager = mcpToken(userWithRole($admin, DefaultRoles::MANAGER));

    expect(mcpResult(mcpCall($manager, 'list_clients'))['json']['total'])->toBe(1);
    foreach ([
        ['create_client', ['legal_name' => 'Otro SA']],
        ['update_client', ['client_id' => $client->id, 'email' => 'x@acme.com']],
        ['change_client_status', ['client_id' => $client->id, 'status' => 'inactive']],
    ] as [$tool, $arguments]) {
        expect(mcpResult(mcpCall($manager, $tool, $arguments)))->toMatchArray(['error' => true]);
    }
    expect($client->fresh()->status->value)->toBe('active')->and(Client::query()->count())->toBe(1);

    // A Colaborador does not even have the clients module.
    expect(mcpResult(mcpCall(mcpToken(userWithRole($admin)), 'list_clients'))['text'])->toContain('Tu rol no tiene acceso a: ');

    $outsider = mcpToken(adminUser('otra@example.com'));
    expect(mcpResult(mcpCall($outsider, 'update_client', ['client_id' => $client->id, 'email' => 'x@acme.com']))['text'])
        ->toContain('No existe el cliente')
        ->and(mcpResult(mcpCall($outsider, 'list_clients'))['json']['total'])->toBe(0);
});

it('stores an emptied field as the web does: null, not an empty string', function () {
    $admin = adminUser();
    $client = Client::factory()->create(['company_id' => $admin->company_id, 'email' => 'hola@acme.com', 'legal_name' => 'Acme SA']);

    mcpCall(mcpToken($admin), 'update_client', ['client_id' => $client->id, 'email' => '', 'trade_name' => '  Acme  ']);

    expect($client->fresh()->only('email', 'trade_name'))->toBe(['email' => null, 'trade_name' => 'Acme']);
});
