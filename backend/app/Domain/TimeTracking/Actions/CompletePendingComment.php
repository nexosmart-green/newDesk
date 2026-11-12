<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\TimerRules;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.17: the owner of an automatically cut entry writes what they did, which unblocks the next timer.
 * Only the owner may (TimeEntryPolicy::completeComment, checked by the web controller and the MCP tool).
 */
final class CompletePendingComment
{
    public function __construct(
        private readonly TimerRules $rules,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $user, TimeEntry $entry, ?string $comment): TimeEntry
    {
        if (! $entry->comment_pending) {
            throw new BusinessRuleViolation('Ese registro ya tiene comentario.', 'comment_not_pending');
        }
        $this->rules->assertValidComment($comment);

        return DB::transaction(function () use ($user, $entry, $comment) {
            $entry->forceFill(['comment' => trim((string) $comment), 'comment_pending' => false])->save();
            ($this->recordActivity)('time_entry.comment_completed', $entry, $user, after: ['comment' => $entry->comment], projectId: $entry->project_id);

            return $entry;
        });
    }
}
