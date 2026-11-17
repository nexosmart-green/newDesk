<?php

namespace App\Domain\Assistant\Contracts;

use App\Domain\Assistant\Models\AssistantWeeklySummary;
use App\Domain\Companies\Models\Company;

/**
 * Where the weekly summary goes (RF-14.06): Discord, mail, the web chat. Each one decides its own recipients from
 * what an Admin configured (CA-14.09) and reports how many it reached (0 = it did not go there).
 */
interface WeeklySummaryChannel
{
    public function key(): string;

    public function deliver(Company $company, AssistantWeeklySummary $summary): int;
}
