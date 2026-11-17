<?php

namespace App\Domain\Monitoring\Jobs;

use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Monitoring\Discord\DiscordError;
use App\Domain\Monitoring\Discord\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One Discord post (monitoreo discord.Queue). The `monitoring-discord` worker is a single process,
 * so messages go out one at a time and in order, and it drains `discord-priority` first: a server,
 * its agent or a service down never waits behind a flood of project notices (D-107). A 429 is
 * released after Discord's `retry_after`; any other failure is logged and dropped, as monitoreo did.
 */
final class SendDiscordMessage implements ShouldQueue
{
    use Queueable;

    public const QUEUE = 'discord';

    public const PRIORITY_QUEUE = 'discord-priority';

    /** Rate-limited this many times in a row: Discord is not taking it. */
    public int $tries = 5;

    public function __construct(public readonly DiscordMessage $message)
    {
        $this->onQueue($message->priority ? self::PRIORITY_QUEUE : self::QUEUE);
    }

    public function handle(DiscordGateway $discord): void
    {
        try {
            $discord->send($this->message);
        } catch (DiscordError $e) {
            if ($e->status === 429) {
                $this->release(max(1, $e->retryAfter));

                return;
            }
            Log::error('discord delivery failed', ['channel' => $this->message->channelId, 'error' => $e->getMessage()]);
        }
    }
}
