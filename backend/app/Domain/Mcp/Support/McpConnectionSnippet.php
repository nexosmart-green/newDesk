<?php

namespace App\Domain\Mcp\Support;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

/**
 * RF-12.04: what a person copies to connect an MCP client (Claude Code) to newDesk.
 */
final class McpConnectionSnippet
{
    public static function endpoint(): string
    {
        return rtrim((string) config('app.url'), '/').'/mcp';
    }

    /** RF-13.10: the monitoreo server takes the same token, for whoever sees monitoring. */
    public static function monitoreoEndpoint(User $user): ?string
    {
        return $user->hasPermission(Permission::MonitoringView) ? self::endpoint().'/monitoreo' : null;
    }

    /** One-line setup; embeds the plain token, so it only exists in the response that issues it. */
    public static function claudeCodeCommand(string $plainTextToken, string $server = 'newdesk', ?string $endpoint = null): string
    {
        $endpoint ??= self::endpoint();

        return "claude mcp add --transport http {$server} {$endpoint} --header \"Authorization: Bearer {$plainTextToken}\"";
    }

    /**
     * Project-level `.mcp.json`: the token stays in the `NEWDESK_TOKEN` env var, never in the repo.
     *
     * @return array{mcpServers: array<string, array{type: string, url: string, headers: array{Authorization: string}}>}
     */
    public static function projectMcpJson(?string $monitoreoEndpoint = null): array
    {
        $server = fn (string $url) => ['type' => 'http', 'url' => $url, 'headers' => ['Authorization' => 'Bearer ${NEWDESK_TOKEN}']];

        return [
            'mcpServers' => [
                'newdesk' => $server(self::endpoint()),
                ...($monitoreoEndpoint === null ? [] : ['monitoreo' => $server($monitoreoEndpoint)]),
            ],
        ];
    }
}
