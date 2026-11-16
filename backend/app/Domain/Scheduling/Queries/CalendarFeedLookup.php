<?php

namespace App\Domain\Scheduling\Queries;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/** RF-06.07: reads of the personal iCal tokens (only hashes are stored). */
final class CalendarFeedLookup
{
    public function isActive(User $user): bool
    {
        return DB::table('calendar_feed_tokens')->where('user_id', $user->id)->whereNull('revoked_at')->exists();
    }

    /** The active owner of a live token, or null (revoked token, unknown token or inactive person). */
    public function userFor(string $token): ?User
    {
        $userId = DB::table('calendar_feed_tokens')
            ->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->value('user_id');

        return $userId !== null ? User::query()->active()->find($userId) : null;
    }
}
