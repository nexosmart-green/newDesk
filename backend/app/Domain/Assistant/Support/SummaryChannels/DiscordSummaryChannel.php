<?php

namespace App\Domain\Assistant\Support\SummaryChannels;

use App\Domain\Assistant\Contracts\WeeklySummaryChannel;
use App\Domain\Assistant\Models\AssistantWeeklySummary;
use App\Domain\Companies\Models\Company;
use App\Domain\Monitoring\Discord\Notifier;

/**
 * The summary channel set up for monitoreo (DISCORD_SUMMARY_CHANNEL_ID), through the same bot and ordered
 * `discord` queue. That channel is the server's, not the company's: the summary (clients, hours per person)
 * goes there only if an Admin of the company opted in (`assistant_summary_discord`, CA-14.09).
 */
final class DiscordSummaryChannel implements WeeklySummaryChannel
{
    public function __construct(private readonly Notifier $notifier) {}

    public function key(): string
    {
        return 'discord';
    }

    public function deliver(Company $company, AssistantWeeklySummary $summary): int
    {
        if (! $company->setting('assistant_summary_discord') || ! $this->notifier->enabled()
            || ! config('newdesk.monitoring.discord.summary_channel_id')) {
            return 0;
        }
        $this->notifier->summary((string) $summary->content);

        return 1;
    }
}
