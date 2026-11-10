<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\TimerRules;

/**
 * RF-05.17: the owner of an automatically cut entry writes what they did, which unblocks the next timer.
 */
final class CompletePendingComment
{
    public function __construct(private readonly TimerRules $rules) {}

    public function __invoke(User $user, TimeEntry $entry, ?string $comment): TimeEntry
    {
        if ($entry->user_id !== $user->id) {
            throw new BusinessRuleViolation('Solo quien registró las horas puede completar su comentario.', 'not_entry_owner', 403);
        }
        if (! $entry->comment_pending) {
            throw new BusinessRuleViolation('Ese registro ya tiene comentario.', 'comment_not_pending');
        }
        $this->rules->assertValidComment($comment);

        $entry->forceFill(['comment' => trim((string) $comment), 'comment_pending' => false])->save();

        return $entry;
    }
}
