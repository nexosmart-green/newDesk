<?php

use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Shared\Activity\ActivitySource;

require_once __DIR__.'/McpTestHelpers.php';

it('lists pending extension requests and approves one loading hours, like the web (RF-05.08, RF-13.07)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $id = $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/extension-requests", ['message' => 'Falta el checkout'])->json('data.id');
    $token = mcpToken($admin);

    $listed = mcpResult(mcpCall($token, 'list_extension_requests'))['json'];
    expect($listed['total'])->toBe(1)
        ->and($listed['requests'][0])->toMatchArray(['id' => $id, 'project' => 'bhn', 'requested_by' => $dev->fullName(), 'message' => 'Falta el checkout'])
        ->and($listed['requests'][0]['project_hours']['allocated'])->toBe('1h 00m')
        ->and(array_column($this->actingAs($admin)->getJson('/api/v1/extension-requests')->json('data'), 'id'))->toBe([$id]);

    $approved = mcpResult(mcpCall($token, 'approve_extension_request', ['request_id' => $id, 'minutes' => 1200, 'reason' => 'Presupuesto #77']))['json']['approved'];
    expect($approved)->toMatchArray(['status' => 'approved'])
        ->and($approved['project_hours']['allocated'])->toBe('21h 00m')
        ->and(ActivityLog::query()->where('event', 'hours.extension_approved')->value('source'))->toBe(ActivitySource::Mcp);

    // Already resolved: same message as the web, nothing loaded twice.
    $web = $this->actingAs($admin)->postJson("/api/v1/extension-requests/{$id}/reject")->assertUnprocessable()->json('message');
    expect(mcpResult(mcpCall($token, 'reject_extension_request', ['request_id' => $id])))->toMatchArray(['error' => true, 'text' => $web])
        ->and($project->fresh()->allocated_seconds)->toBe(21 * 3600);
});

it('keeps extension requests to hours.allocate and to the own company', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 0);
    $id = $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/extension-requests")->json('data.id');
    $devToken = mcpToken($dev);

    expect(mcpResult(mcpCall($devToken, 'list_extension_requests'))['error'])->toBeTrue()
        ->and(mcpResult(mcpCall($devToken, 'reject_extension_request', ['request_id' => $id]))['text'])->toBe('Tu rol no tiene permiso para cargar horas.')
        ->and(mcpResult(mcpCall($devToken, 'approve_extension_request', ['request_id' => $id, 'minutes' => 60, 'reason' => 'Me apruebo']))['error'])->toBeTrue();

    $outsider = mcpToken(adminUser('otra@example.com'));
    expect(mcpResult(mcpCall($outsider, 'reject_extension_request', ['request_id' => $id]))['text'])->toContain('No existe el pedido de ampliación')
        ->and(mcpResult(mcpCall($outsider, 'list_extension_requests'))['json']['total'])->toBe(0)
        ->and($this->actingAs($admin)->getJson('/api/v1/extension-requests')->json('data.0.status'))->toBe('pending');
});
