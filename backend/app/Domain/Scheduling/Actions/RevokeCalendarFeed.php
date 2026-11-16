<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/** RF-06.07: the old feed URL stops working right away. */
final class RevokeCalendarFeed
{
    public function __invoke(User $user): void
    {
        DB::table('calendar_feed_tokens')->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
