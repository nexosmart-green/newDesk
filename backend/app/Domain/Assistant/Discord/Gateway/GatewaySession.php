<?php

namespace App\Domain\Assistant\Discord\Gateway;

/**
 * Discord's gateway protocol for the assistant's DMs (RF-14.08, phase 5), without any socket: frames in, actions out,
 * so every rule is testable. https://discord.com/developers/docs/events/gateway
 *
 * The runner sends what `send` says, beats every `heartbeat` milliseconds (first beat with jitter), reconnects when
 * told, and hands each `dispatch` to the app. The payloads carry the bot token: they are never logged.
 */
final class GatewaySession
{
    public const DEFAULT_URL = 'wss://gateway.discord.gg/?v=10&encoding=json';

    /** DIRECT_MESSAGES only: a DM's content reaches the bot without the privileged MESSAGE_CONTENT intent. */
    public const INTENTS = 1 << 12;

    private const DISPATCH = 0;

    private const HEARTBEAT = 1;

    private const IDENTIFY = 2;

    private const RESUME = 6;

    private const RECONNECT = 7;

    private const INVALID_SESSION = 9;

    private const HELLO = 10;

    private const HEARTBEAT_ACK = 11;

    /** Close codes after which reconnecting cannot help: bad token, shard/intents misconfigured. */
    private const FATAL_CLOSE_CODES = [4004, 4010, 4011, 4012, 4013, 4014];

    /** Close codes after which the session cannot be resumed: identify again. */
    private const FRESH_CLOSE_CODES = [4007, 4009];

    private ?int $sequence = null;

    private ?string $sessionId = null;

    private ?string $resumeUrl = null;

    private bool $awaitingAck = false;

    public function __construct(private readonly string $token) {}

    /** Where to connect: the session's resume URL while it can be resumed, else the default gateway. */
    public function url(): string
    {
        return $this->canResume() ? $this->resumeUrl.'/?v=10&encoding=json' : self::DEFAULT_URL;
    }

    /**
     * @param  array<string, mixed>  $frame
     * @return list<GatewayAction>
     */
    public function receive(array $frame): array
    {
        if (isset($frame['s']) && is_int($frame['s'])) {
            $this->sequence = $frame['s'];
        }

        return match ($frame['op'] ?? null) {
            self::HELLO => $this->hello((int) ($frame['d']['heartbeat_interval'] ?? 41250)),
            self::HEARTBEAT_ACK => $this->acknowledged(),
            self::HEARTBEAT => [GatewayAction::send($this->beatPayload())],
            self::RECONNECT => [GatewayAction::reconnect()],
            self::INVALID_SESSION => $this->invalidSession((bool) ($frame['d'] ?? false)),
            self::DISPATCH => $this->dispatch((string) ($frame['t'] ?? ''), is_array($frame['d'] ?? null) ? $frame['d'] : []),
            default => [],
        };
    }

    /**
     * The heartbeat timer fired. No ACK since the last beat means a zombie connection: reconnect (and resume).
     *
     * @return list<GatewayAction>
     */
    public function beat(): array
    {
        if ($this->awaitingAck) {
            $this->awaitingAck = false;

            return [GatewayAction::reconnect()];
        }
        $this->awaitingAck = true;

        return [GatewayAction::send($this->beatPayload())];
    }

    /**
     * The socket closed. Fatal codes stop the process (supervisor shows it); others reconnect.
     *
     * @return list<GatewayAction>
     */
    public function closed(int $code): array
    {
        $this->awaitingAck = false;
        if (in_array($code, self::FATAL_CLOSE_CODES, true)) {
            return [GatewayAction::stop("Discord cerró el gateway con {$code}: revisá el token del bot y los intents.")];
        }
        if (in_array($code, self::FRESH_CLOSE_CODES, true)) {
            $this->forget();
        }

        return [GatewayAction::reconnect()];
    }

    /** @return list<GatewayAction> */
    private function hello(int $interval): array
    {
        $this->awaitingAck = false;
        $start = $this->canResume()
            ? ['op' => self::RESUME, 'd' => ['token' => $this->token, 'session_id' => $this->sessionId, 'seq' => $this->sequence]]
            : ['op' => self::IDENTIFY, 'd' => [
                'token' => $this->token,
                'intents' => self::INTENTS,
                'properties' => ['os' => PHP_OS_FAMILY, 'browser' => 'newdesk', 'device' => 'newdesk'],
            ]];

        return [GatewayAction::heartbeat($interval), GatewayAction::send($start)];
    }

    /** @return list<GatewayAction> */
    private function acknowledged(): array
    {
        $this->awaitingAck = false;

        return [];
    }

    /** @return list<GatewayAction> */
    private function invalidSession(bool $resumable): array
    {
        if (! $resumable) {
            $this->forget();
        }

        return [GatewayAction::reconnect()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<GatewayAction>
     */
    private function dispatch(string $event, array $data): array
    {
        if ($event === 'READY') {
            $this->sessionId = is_string($data['session_id'] ?? null) ? $data['session_id'] : null;
            $this->resumeUrl = is_string($data['resume_gateway_url'] ?? null) ? rtrim($data['resume_gateway_url'], '/') : null;

            return [];
        }

        return $event === '' ? [] : [GatewayAction::dispatch($event, $data)];
    }

    /** @return array<string, mixed> */
    private function beatPayload(): array
    {
        return ['op' => self::HEARTBEAT, 'd' => $this->sequence];
    }

    private function canResume(): bool
    {
        return $this->sessionId !== null && $this->resumeUrl !== null && $this->sequence !== null;
    }

    private function forget(): void
    {
        $this->sessionId = null;
        $this->resumeUrl = null;
        $this->sequence = null;
    }
}
