<?php

namespace App\Domain\Assistant\Support\SummaryChannels;

use App\Domain\Assistant\Contracts\WeeklySummaryChannel;
use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Enums\MessageRole;
use App\Domain\Assistant\Enums\MessageStatus;
use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantWeeklySummary;
use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The summary also stays in the web chat, as its own conversation, of whoever uses the assistant AND may see the
 * company's hours report (the same gate as the weekly_summary tool): team users only, never a client (CA-14.09).
 * All or nothing, so a retry never leaves some people with two copies.
 */
final class WebSummaryChannel implements WeeklySummaryChannel
{
    public function key(): string
    {
        return 'web';
    }

    public function deliver(Company $company, AssistantWeeklySummary $summary): int
    {
        $people = User::query()->where('company_id', $company->id)->active()->with('role')->get()
            ->filter(fn (User $person) => ! $person->isClient()
                && $person->hasPermission(Permission::AssistantUse)
                && $person->hasPermission(Permission::ProjectsManage)
                && $person->hasPermission(Permission::ModuleReports));
        $title = 'Resumen de la semana del '.$summary->week_from->format('d/m').' al '.$summary->week_to->format('d/m');

        DB::transaction(function () use ($people, $company, $summary, $title) {
            foreach ($people as $person) {
                $conversation = AssistantConversation::query()->create([
                    'company_id' => $company->id, 'user_id' => $person->id, 'channel' => AssistantChannel::Web,
                    'title' => $title, 'last_message_at' => now(),
                ]);
                // Its cost is the summary's (counted once in the cap), not each copy's.
                $conversation->messages()->create([
                    'company_id' => $company->id, 'role' => MessageRole::Assistant, 'status' => MessageStatus::Done, 'content' => $summary->content,
                ]);
            }
        });

        return $people->count();
    }
}
