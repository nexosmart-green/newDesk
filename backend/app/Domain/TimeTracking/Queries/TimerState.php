<?php

namespace App\Domain\TimeTracking\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Database\Eloquent\Collection;

/**
 * A person's timer state (RF-05.01/05.17), identical for the header timer, `whoami` and `get_active_timer`:
 * the running entry, if any, and the entries still waiting for their comment, oldest first.
 */
final class TimerState
{
    private const RELATIONS = ['task:id,code,title,project_id', 'project:id,name,code_prefix', 'user:id,first_name,last_name'];

    /** @return array{active: TimeEntry|null, pending: Collection<int, TimeEntry>} */
    public function __invoke(User $user): array
    {
        return ['active' => $this->active($user), 'pending' => $this->pending($user)];
    }

    public function active(User $user): ?TimeEntry
    {
        return TimeEntry::query()->runningFor($user)->with(self::RELATIONS)->first();
    }

    /** @return Collection<int, TimeEntry> */
    public function pending(User $user): Collection
    {
        return TimeEntry::query()->forCompany($user->company_id)
            ->where('user_id', $user->id)
            ->where('comment_pending', true)
            ->with(self::RELATIONS)
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();
    }
}
