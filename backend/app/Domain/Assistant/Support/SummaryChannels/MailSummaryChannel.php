<?php

namespace App\Domain\Assistant\Support\SummaryChannels;

use App\Domain\Assistant\Contracts\WeeklySummaryChannel;
use App\Domain\Assistant\Models\AssistantWeeklySummary;
use App\Domain\Assistant\Notifications\WeeklySummaryNotification;
use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * The addresses an Admin listed (`assistant_summary_emails`); with none, the company's active Admins.
 * Never anyone else: the summary carries client names and hours per person (CA-14.09).
 */
final class MailSummaryChannel implements WeeklySummaryChannel
{
    public function key(): string
    {
        return 'mail';
    }

    public function deliver(Company $company, AssistantWeeklySummary $summary): int
    {
        $recipients = $this->recipients($company);
        foreach ($recipients as $email) {
            Notification::route('mail', $email)->notify(new WeeklySummaryNotification($company->name, $summary));
        }

        return count($recipients);
    }

    /** @return list<string> */
    public function recipients(Company $company): array
    {
        $configured = array_values(array_filter((array) $company->setting('assistant_summary_emails')));
        if ($configured !== []) {
            return $configured;
        }

        return User::query()->where('company_id', $company->id)->active()
            ->whereHas('role', fn ($role) => $role->where('is_system', true))
            ->pluck('email')->all();
    }
}
