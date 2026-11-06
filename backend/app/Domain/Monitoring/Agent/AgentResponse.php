<?php

namespace App\Domain\Monitoring\Agent;

use Carbon\CarbonImmutable;

/**
 * One agent answer (contract.Response). `data` is decoded with objects kept as objects,
 * so re-encoding it gives back the agent's JSON (`{}` stays `{}`).
 */
final readonly class AgentResponse
{
    /** @param  list<string>  $warnings */
    public function __construct(
        public ?CarbonImmutable $collectedAt,
        public string $agentVersion,
        public mixed $data,
        public array $warnings = [],
    ) {}

    public static function fromJson(string $body): self
    {
        $decoded = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        if (! is_object($decoded)) {
            throw new \JsonException('the agent answer is not an object');
        }

        $collectedAt = isset($decoded->collected_at) && is_string($decoded->collected_at)
            ? CarbonImmutable::parse($decoded->collected_at)->utc()
            : null;

        return new self(
            // Go's zero time means "not set".
            $collectedAt !== null && $collectedAt->year > 1 ? $collectedAt : null,
            is_string($decoded->agent_version ?? null) ? $decoded->agent_version : '',
            $decoded->data ?? null,
            array_values(array_filter((array) ($decoded->warnings ?? []), 'is_string')),
        );
    }

    public function dataJson(): string
    {
        return json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
