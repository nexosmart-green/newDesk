<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RF-06.07 / CA-06.07: personal, revocable iCal token. Issuing a new one revokes the previous.
 * Only the hash is stored.
 */
final class IssueCalendarFeed
{
    public function __construct(private readonly RevokeCalendarFeed $revoke) {}

    /** @return string the plain token (shown once, embedded in the feed URL) */
    public function __invoke(User $user): string
    {
        $token = Str::random(48);

        DB::transaction(function () use ($user, $token) {
            ($this->revoke)($user);
            DB::table('calendar_feed_tokens')->insert([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $token;
    }
}
