<?php

namespace App\Domain\Assistant\Discord\Gateway;

/** What the gateway runner must do next, as decided by GatewaySession. */
final class GatewayAction
{
    public const SEND = 'send';

    public const HEARTBEAT = 'heartbeat';

    public const RECONNECT = 'reconnect';

    public const DISPATCH = 'dispatch';

    public const STOP = 'stop';

    /** @param  array<string, mixed>  $payload */
    private function __construct(
        public readonly string $type,
        public readonly array $payload = [],
        public readonly int $intervalMs = 0,
        public readonly string $event = '',
        public readonly string $reason = '',
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function send(array $payload): self
    {
        return new self(self::SEND, payload: $payload);
    }

    public static function heartbeat(int $intervalMs): self
    {
        return new self(self::HEARTBEAT, intervalMs: $intervalMs);
    }

    public static function reconnect(): self
    {
        return new self(self::RECONNECT);
    }

    /** @param  array<string, mixed>  $data */
    public static function dispatch(string $event, array $data): self
    {
        return new self(self::DISPATCH, payload: $data, event: $event);
    }

    public static function stop(string $reason): self
    {
        return new self(self::STOP, reason: $reason);
    }
}
