<?php

namespace App\Domain\Monitoring\Contracts;

use App\Domain\Monitoring\Agent\AgentError;
use App\Domain\Monitoring\Agent\AgentResponse;
use App\Domain\Monitoring\Agent\AgentTarget;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * HTTPS to monitor-agent (ADR-0002 §2). Calls are asynchronous so one process polls every
 * server at once: promises progress when tick() runs, or when one is waited on.
 */
interface AgentGateway
{
    /**
     * Derives the public-key pin from a TLS handshake that only completes when the
     * certificate has this SHA-256 fingerprint. No HTTP byte is sent.
     *
     * @throws AgentError pin_mismatch when the certificate is another one
     */
    public function pin(string $ip, int $port, string $fingerprint): string;

    /**
     * GET /v1/<section> with the bearer token, refused by curl unless the pin matches.
     *
     * @return PromiseInterface<AgentResponse> rejected with AgentError
     */
    public function fetch(AgentTarget $target, string $section): PromiseInterface;

    /**
     * TCP connect only (SSH port probe, CA-13.03).
     *
     * @return PromiseInterface<bool> true when the port accepted the connection
     */
    public function probe(string $ip, int $port, int $timeoutSeconds): PromiseInterface;

    /** Moves in-flight calls forward without blocking for long. */
    public function tick(): void;
}
