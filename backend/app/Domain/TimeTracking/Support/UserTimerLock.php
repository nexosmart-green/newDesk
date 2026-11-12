<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Serializes timer operations of one person across tabs, devices and the MCP (R-2, RF-05.03).
 * The UNIQUE active_user_id column is the last line of defense; this lock avoids racing into it.
 */
final class UserTimerLock
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(int $userId, callable $callback): mixed
    {
        try {
            return Cache::lock("timer:user:{$userId}", 15)->block(10, $callback);
        } catch (LockTimeoutException) {
            throw new BusinessRuleViolation('Otro pedido de cronómetro estaba en curso. Esperá un segundo y probá de nuevo.', 'timer_busy', 409);
        }
    }
}
