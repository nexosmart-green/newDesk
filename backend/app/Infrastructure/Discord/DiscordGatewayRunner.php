<?php

namespace App\Infrastructure\Discord;

use App\Domain\Assistant\Discord\Gateway\GatewayAction;
use App\Domain\Assistant\Discord\Gateway\GatewaySession;
use Closure;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Throwable;

/**
 * The socket side of the assistant's DMs (RF-14.08 phase 5): connects to Discord's gateway and does what
 * GatewaySession decides — send, beat, reconnect (1 s doubling to 60 s), stop. Events go to `$onEvent`, which must
 * be quick (it queues a job): a slow handler would delay the heartbeat. Nothing here logs a payload: they carry the
 * bot token.
 */
final class DiscordGatewayRunner
{
    /** Ours, not 1000: Discord keeps a session closed with any other code resumable. */
    private const RECONNECT_CLOSE = 4000;

    private const MAX_BACKOFF_SECONDS = 60;

    private const HELLO_TIMEOUT_SECONDS = 30;

    private LoopInterface $loop;

    private GatewaySession $session;

    private ?WebSocket $socket = null;

    private ?TimerInterface $heartbeat = null;

    /** The close we asked for is not news: the reconnect is already scheduled. */
    private bool $closingOnPurpose = false;

    private int $failures = 0;

    private int $exitCode = 0;

    /** @param  Closure(string, array<string, mixed>): void  $onEvent */
    public function __construct(private readonly string $token, private readonly Closure $onEvent) {}

    /** Runs until stopped (SIGTERM/SIGINT) or Discord refuses the token. 0 when stopped, 1 when refused. */
    public function run(): int
    {
        $this->loop = Loop::get();
        $this->session = new GatewaySession($this->token);
        if (function_exists('pcntl_signal')) { // Linux (production); Windows has no signals to catch
            foreach ([SIGTERM, SIGINT] as $signal) {
                $this->loop->addSignal($signal, fn () => $this->shutdown());
            }
        }
        $this->connect();
        $this->loop->run();

        return $this->exitCode;
    }

    private function connect(): void
    {
        (new Connector($this->loop))($this->session->url())->then(
            function (WebSocket $socket) {
                $this->socket = $socket;
                $this->closingOnPurpose = false;
                // Discord says HELLO right away; an open socket that stays silent is as good as dead.
                $this->heartbeat = $this->loop->addTimer(self::HELLO_TIMEOUT_SECONDS, fn () => $this->reconnect());
                $socket->on('message', fn (MessageInterface $message) => $this->onFrame((string) $message));
                $socket->on('close', fn (?int $code = null) => $this->onClose($code ?? 1006));
            },
            function (Throwable $e) {
                Log::warning('Discord gateway: no se pudo conectar ('.class_basename($e).').');
                $this->reconnectLater();
            },
        );
    }

    private function onFrame(string $raw): void
    {
        $frame = json_decode($raw, true);
        if (! is_array($frame)) {
            return;
        }
        if (($frame['t'] ?? null) === 'READY' || ($frame['t'] ?? null) === 'RESUMED') {
            $this->failures = 0;
            Log::info('Discord gateway: conectado ('.$frame['t'].').');
        }
        $this->apply($this->session->receive($frame));
    }

    private function onClose(int $code): void
    {
        $this->stopBeating();
        $this->socket = null;
        if ($this->closingOnPurpose) {
            return;
        }
        Log::warning("Discord gateway: se cerró la conexión ({$code}).");
        $this->apply($this->session->closed($code));
    }

    /** @param  list<GatewayAction>  $actions */
    private function apply(array $actions): void
    {
        foreach ($actions as $action) {
            match ($action->type) {
                GatewayAction::SEND => $this->socket?->send(json_encode($action->payload, JSON_THROW_ON_ERROR)),
                GatewayAction::HEARTBEAT => $this->startBeating($action->intervalMs),
                GatewayAction::RECONNECT => $this->reconnect(),
                GatewayAction::DISPATCH => $this->dispatch($action->event, $action->payload),
                GatewayAction::STOP => $this->fail($action->reason),
                default => null,
            };
        }
    }

    /** Discord asks for the first beat after a random part of the interval, then every interval. */
    private function startBeating(int $intervalMs): void
    {
        $this->stopBeating();
        $seconds = max(1, $intervalMs) / 1000;
        $this->heartbeat = $this->loop->addTimer($seconds * mt_rand(1, 100) / 100, function () use ($seconds) {
            $this->apply($this->session->beat());
            $this->heartbeat = $this->loop->addPeriodicTimer($seconds, fn () => $this->apply($this->session->beat()));
        });
    }

    private function stopBeating(): void
    {
        if ($this->heartbeat !== null) {
            $this->loop->cancelTimer($this->heartbeat);
            $this->heartbeat = null;
        }
    }

    private function reconnect(): void
    {
        $this->stopBeating();
        if ($this->socket !== null) {
            $this->closingOnPurpose = true;
            $this->socket->close(self::RECONNECT_CLOSE);
            $this->socket = null;
        }
        $this->reconnectLater();
    }

    private function reconnectLater(): void
    {
        $seconds = min(self::MAX_BACKOFF_SECONDS, 2 ** $this->failures) + mt_rand(0, 1000) / 1000;
        $this->failures = min($this->failures + 1, 6);
        $this->loop->addTimer($seconds, fn () => $this->connect());
    }

    /** @param  array<string, mixed>  $data */
    private function dispatch(string $event, array $data): void
    {
        try {
            ($this->onEvent)($event, $data);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function fail(string $reason): void
    {
        Log::error("Discord gateway: {$reason}");
        $this->exitCode = 1;
        $this->shutdown();
    }

    private function shutdown(): void
    {
        $this->stopBeating();
        $this->closingOnPurpose = true;
        $this->socket?->close(1000);
        $this->loop->stop();
    }
}
