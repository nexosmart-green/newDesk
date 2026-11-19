<?php

namespace App\Domain\Shared\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RNF-12: "something of yours changed" on the person's private channel, so every open tab and device refreshes
 * at once instead of waiting for the poll. Only the kind travels; the browser asks again with its own permissions.
 * After commit (right away outside a transaction): nobody refetches something that rolled back.
 */
final class UserSignal implements ShouldBroadcastNow
{
    /** Their timer, day clock or entries changed: timer state, Mi semana, Mis horas, bags. */
    public const TIMER = 'timer';

    /** A new notification in their bell. */
    public const NOTIFICATIONS = 'notifications';

    public function __construct(public readonly int $userId, public readonly string $kind) {}

    /** Realtime is best effort: Reverb being down never fails the write that just happened. One per commit and kind. */
    public static function send(int $userId, string $kind): void
    {
        $pending = app(PendingUserSignals::class);
        if (! $pending->claim($userId, $kind)) {
            return;
        }

        DB::afterCommit(function () use ($pending, $userId, $kind) {
            $pending->release($userId, $kind);
            try {
                event(new self($userId, $kind));
            } catch (Throwable $e) {
                Log::warning('realtime: user signal failed', ['user' => $userId, 'kind' => $kind, 'error' => $e->getMessage()]);
            }
        });
    }

    public static function channelName(int $userId): string
    {
        return "user.{$userId}";
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->userId))];
    }

    public function broadcastAs(): string
    {
        return $this->kind;
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
