<?php

namespace App\Domain\Assistant\Queries;

use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Models\AssistantWeeklySummary;
use App\Domain\Companies\Models\Company;
use Illuminate\Support\Carbon;

/**
 * RF-14.09: what the assistant spent this month (the company's month), as Claude Code reported it: its answers
 * plus the weekly summaries it wrote. With a subscription it is an estimate: the cap is a usage guard.
 */
final class AssistantMonthlySpend
{
    public function __invoke(Company $company, ?Carbon $now = null): float
    {
        $start = ($now ?? now())->copy()->setTimezone($company->timezone)->startOfMonth()->utc();

        return (float) AssistantMessage::query()->forCompany($company->id)->where('created_at', '>=', $start)->sum('cost_usd')
            + (float) AssistantWeeklySummary::query()->forCompany($company->id)->where('created_at', '>=', $start)->sum('cost_usd');
    }
}
