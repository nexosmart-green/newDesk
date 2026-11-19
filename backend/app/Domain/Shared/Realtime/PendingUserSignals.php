<?php

namespace App\Domain\Shared\Realtime;

/**
 * Signals already waiting for the commit in this request or job: splitting a day into five entries rings the
 * person once, not five times. Bound as `scoped`, so a rolled-back transaction (whose callback never runs) can't
 * hold a key past the request or job.
 */
final class PendingUserSignals
{
    /** @var array<string, true> */
    private array $pending = [];

    /** False when the same signal is already on its way. */
    public function claim(int $userId, string $kind): bool
    {
        $key = "{$userId}:{$kind}";
        if (isset($this->pending[$key])) {
            return false;
        }
        $this->pending[$key] = true;

        return true;
    }

    public function release(int $userId, string $kind): void
    {
        unset($this->pending["{$userId}:{$kind}"]);
    }
}
