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
    /** @return string the plain token (shown once, embedded in the feed URL) */
    public function issue(User $user): string
    {
        $token = Str::random(48);

        DB::transaction(function () use ($user, $token) {
            $this->revoke($user);
            DB::table('calendar_feed_tokens')->insert([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $token;
    }

    public function revoke(User $user): void
    {
        DB::table('calendar_feed_tokens')->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function userFor(string $token): ?User
    {
        $userId = DB::table('calendar_feed_tokens')
            ->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->value('user_id');

        $user = $userId !== null ? User::query()->find($userId) : null;

        return $user?->isActive() ? $user : null;
    }
}
