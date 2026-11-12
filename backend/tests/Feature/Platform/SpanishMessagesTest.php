<?php

use Illuminate\Testing\TestResponse;

require_once __DIR__.'/../Mcp/McpTestHelpers.php';

/** Raw translation keys or Laravel's English defaults: what a person (or the AI) must never read. */
function untranslatedTexts(array $texts): array
{
    return array_values(array_filter($texts, fn (string $text) => str_contains($text, 'validation.')
        || preg_match('/\b(the|field|must|required|invalid|selected|characters|kilobytes|format)\b/i', $text) === 1));
}

/** @return list<string> the envelope's message plus every field error */
function envelopeTexts(TestResponse $response): array
{
    return [(string) $response->json('message'), ...collect($response->json('errors') ?? [])->flatten()->all()];
}

it('answers every validation error in Spanish, with field names people understand (web)', function () {
    $admin = adminUser();
    ['project' => $project, 'task' => $task] = bagProject($admin, [$admin], 3600);
    $this->actingAs($admin);

    $requests = [
        ['post', '/api/v1/users', ['email' => 'no-es-un-email', 'role_id' => 999999]],
        ['put', '/api/v1/company', ['name' => str_repeat('x', 200), 'currency' => 'pesos', 'timezone' => 'Marte/Olympus', 'settings' => ['hour_alert_thresholds' => [90, 50]]]],
        ['post', '/api/v1/clients', ['legal_name' => '', 'email' => 'x', 'tax_id' => '123']],
        ['post', '/api/v1/projects', ['code_prefix' => '1', 'type' => 'otro', 'ends_on' => 'mañana', 'member_ids' => [1, 1]]],
        ['put', "/api/v1/projects/{$project->id}", ['starts_on' => '2026-12-10', 'ends_on' => '2026-12-01']],
        ['post', "/api/v1/projects/{$project->id}/tasks", ['priority' => 'altísima', 'estimate_seconds' => -5, 'label_ids' => [999999]]],
        ['post', "/api/v1/tasks/{$task->code}/time-entries", ['date' => '11/11/2026', 'from' => '9', 'to' => '25:00']],
        ['post', "/api/v1/projects/{$project->id}/allocations", ['seconds' => 0]],
        ['post', '/api/v1/shift-templates', ['starts_at' => '09:00', 'ends_at' => '09:00', 'color' => 'rojo', 'mode' => 'x']],
        ['post', '/api/v1/shifts/assign', ['user_ids' => [], 'from' => '2026-12-10', 'to' => '2026-12-01']],
        ['get', '/api/v1/me/timesheet?from=ayer', []],
        ['get', '/api/v1/team-calendar?from=2026-11-01', []],
        ['put', '/api/v1/me/skills', ['skills' => [['id' => 'a', 'level' => 500]]]],
        ['put', '/api/v1/me/notification-preferences', ['preferences' => [['event' => 'nada']]]],
        ['post', '/api/v1/me/ai-tokens', ['expires_in_days' => 7]],
        ['post', '/api/v1/me/two-factor/confirm', []],
    ];

    foreach ($requests as [$method, $url, $payload]) {
        $response = $this->json($method, $url, $payload);

        expect($response->status())->toBe(422, "{$method} {$url}")
            ->and(untranslatedTexts(envelopeTexts($response)))->toBe([], "{$method} {$url}")
            ->and($response->json('message'))->toBe('Revisá los campos marcados: hay datos que faltan o no son correctos.');
    }
});

it('answers the login and password forms in Spanish', function () {
    adminUser();

    foreach ([
        ['/api/v1/auth/login', []],
        ['/api/v1/auth/forgot-password', ['email' => 'x']],
        ['/api/v1/auth/reset-password', ['password' => 'corta', 'password_confirmation' => 'otra']],
    ] as [$url, $payload]) {
        $response = $this->postJson($url, $payload)->assertUnprocessable();

        expect(untranslatedTexts(envelopeTexts($response)))->toBe([], $url);
    }

    $this->postJson('/api/v1/auth/reset-password', ['token' => 't', 'email' => 'a@b.com', 'password' => 'corta', 'password_confirmation' => 'corta'])
        ->assertJsonPath('errors.password', [
            'La contraseña tiene que tener al menos 10 caracteres.',
            'La contraseña tiene que tener al menos un número.',
        ]);
});

it('answers MCP tool validation in Spanish, so the model knows what to fix', function () {
    $dev = userWithRole(adminUser());
    $token = mcpToken($dev);

    foreach ([
        ['log_time', ['code' => 'bhn-1', 'date' => '11/11/2026', 'from' => '9']],
        ['my_timesheet', ['from' => '2026-11-10', 'to' => '2026-11-01']],
        ['my_shifts', []],
        ['create_task', ['priority' => 'altísima']],
        ['update_checklist', ['code' => 'bhn-1', 'action' => 'borrar']],
    ] as [$tool, $arguments]) {
        $result = mcpResult(mcpCall($token, $tool, $arguments));

        expect($result['error'])->toBeTrue($tool)
            ->and(untranslatedTexts([$result['text']]))->toBe([], $tool);
    }
});

it('says how to split a range that is too long, web and MCP alike', function () {
    $dev = userWithRole(adminUser());
    $message = 'Elegí un rango de hasta 62 días (pediste 63). Si necesitás más, consultalo por partes: un período a continuación del otro.';

    $this->actingAs($dev)->getJson('/api/v1/me/timesheet?from=2026-11-01&to=2027-01-02')->assertUnprocessable()->assertJsonPath('message', $message);
    $this->actingAs($dev)->getJson('/api/v1/me/timesheet?from=2026-11-01&to=2027-01-01')->assertOk();
    expect(mcpResult(mcpCall(mcpToken($dev), 'my_timesheet', ['from' => '2026-11-01', 'to' => '2027-01-02']))['text'])->toContain($message);
});
