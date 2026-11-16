<?php

namespace App\Domain\Shared\Activity;

/**
 * Request-scoped channel info (bound as `scoped` in the container). The HTTP layer
 * defaults to Web; the MCP server switches it to Mcp with the token name (RF-12.06),
 * so domain actions record the right source without knowing who called them.
 */
final class ActivityContext
{
    public ActivitySource $source = ActivitySource::Web;

    public ?string $tokenName = null;

    public function useMcp(?string $tokenName): void
    {
        $this->source = ActivitySource::Mcp;
        $this->tokenName = $tokenName;
    }

    /** @param  string  $channel  where the person confirmed: web | discord */
    public function useAssistant(string $channel): void
    {
        $this->source = ActivitySource::Assistant;
        $this->tokenName = $channel;
    }

    public function useSystem(): void
    {
        $this->source = ActivitySource::System;
        $this->tokenName = null;
    }
}
