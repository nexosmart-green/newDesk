<?php

namespace App\Domain\Mcp\Support;

/**
 * RF-12.04: what a person copies to connect an MCP client (Claude Code) to newDesk.
 */
final class McpConnectionSnippet
{
    public static function endpoint(): string
    {
        return rtrim((string) config('app.url'), '/').'/mcp';
    }

    /** One-line setup; embeds the plain token, so it only exists in the response that issues it. */
    public static function claudeCodeCommand(string $plainTextToken): string
    {
        $endpoint = self::endpoint();

        return "claude mcp add --transport http newdesk {$endpoint} --header \"Authorization: Bearer {$plainTextToken}\"";
    }

    /**
     * Project-level `.mcp.json`: the token stays in the `NEWDESK_TOKEN` env var, never in the repo.
     *
     * @return array{mcpServers: array{newdesk: array{type: string, url: string, headers: array{Authorization: string}}}}
     */
    public static function projectMcpJson(): array
    {
        return [
            'mcpServers' => [
                'newdesk' => [
                    'type' => 'http',
                    'url' => self::endpoint(),
                    'headers' => ['Authorization' => 'Bearer ${NEWDESK_TOKEN}'],
                ],
            ],
        ];
    }
}
