<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Contracts\WeeklySummaryChannel;
use App\Domain\Assistant\Models\AssistantWeeklySummary;
use App\Domain\Assistant\Queries\WeeklySummaryData;
use App\Domain\Companies\Models\Company;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * RF-14.06: last week's summary for a company, kept once per company and week.
 *  1. Reserve the row first (unique company+week): only the run that claims it writes the text, so two servers
 *     never pay the AI twice and a lost race loses no cost.
 *  2. Hand it to every channel separately: one failing (mail queue, Discord) neither stops the others nor marks
 *     the summary as sent; the next hourly run retries only the channels that did not make it.
 */
final class SendWeeklySummary
{
    /** A claim this old belongs to a run that died while writing: another run may take it over. */
    private const STALE_CLAIM_MINUTES = 15;

    /** @param  iterable<WeeklySummaryChannel>  $channels */
    public function __construct(
        private readonly WeeklySummaryData $data,
        private readonly ComposeWeeklySummary $compose,
        private readonly iterable $channels,
    ) {}

    /** @return AssistantWeeklySummary|null null when there was nothing left to do for this week */
    public function __invoke(Company $company, ?Carbon $now = null): ?AssistantWeeklySummary
    {
        [$from, $to] = WeeklySummaryData::lastWeek($company, $now);
        $summary = $this->reserve($company, $from, $to);

        if ($summary->content === null) {
            if (! $this->claim($summary)) {
                return null; // another run is writing it right now
            }
            $text = ($this->compose)($company, ($this->data)($company, $now));
            $summary->forceFill(['content' => $text['text'], 'written_by' => $text['written_by'], 'cost_usd' => $text['cost_usd']])->save();
        }

        return $this->deliver($company, $summary) ? $summary->refresh() : null;
    }

    private function reserve(Company $company, Carbon $from, Carbon $to): AssistantWeeklySummary
    {
        $existing = fn () => AssistantWeeklySummary::query()->forCompany($company->id)->whereDate('week_from', $from->toDateString())->first();

        try {
            return $existing() ?? AssistantWeeklySummary::query()->create([
                'company_id' => $company->id, 'week_from' => $from->toDateString(), 'week_to' => $to->toDateString(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $existing();
        }
    }

    /** Atomic: exactly one run moves it to "composing" (or takes over a claim left by a dead run). */
    private function claim(AssistantWeeklySummary $summary): bool
    {
        return AssistantWeeklySummary::query()->withoutGlobalScopes()->whereKey($summary->id)->whereNull('content')
            ->where(fn ($query) => $query->whereNull('written_by')
                ->orWhere('updated_at', '<', now()->subMinutes(self::STALE_CLAIM_MINUTES)))
            ->update(['written_by' => 'composing', 'updated_at' => now()]) === 1;
    }

    /** @return bool whether any channel was tried in this run */
    private function deliver(Company $company, AssistantWeeklySummary $summary): bool
    {
        $state = $summary->channels ?? [];
        $tried = false;

        foreach ($this->channels as $channel) {
            if (isset($state[$channel->key()]['reached'])) {
                continue;
            }
            $tried = true;
            try {
                $state[$channel->key()] = ['reached' => $channel->deliver($company, $summary)];
            } catch (Throwable $e) {
                report($e);
                $state[$channel->key()] = ['error' => mb_substr($e->getMessage(), 0, 200)];
            }
            $summary->forceFill(['channels' => $state])->save();
        }

        return $tried;
    }
}
