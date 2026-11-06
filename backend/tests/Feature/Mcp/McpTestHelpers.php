<?php

use App\Domain\Identity\Models\User;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/../TimeTracking/TimeTrackingHelpers.php';

function mcpToken(User $user, string $name = 'laptop'): string
{
    return $user->createToken($name, ['mcp'])->plainTextToken;
}

/** JSON-RPC `tools/call` over the real HTTP transport, like Claude Code does. */
function mcpCall(string $token, string $tool, array $arguments = []): TestResponse
{
    // Each call is a fresh request: no cached auth from previous ones.
    app('auth')->forgetGuards();

    return test()->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json, text/event-stream'])
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => (object) $arguments],
        ]);
}

/** @return array{error: bool, text: string, json: mixed} */
function mcpResult(TestResponse $response): array
{
    $result = $response->json('result');
    $text = $result['content'][0]['text'] ?? '';

    return ['error' => (bool) ($result['isError'] ?? false), 'text' => $text, 'json' => json_decode($text, true)];
}
