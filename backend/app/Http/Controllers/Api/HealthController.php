<?php

namespace App\Http\Controllers\Api;

use App\Domain\Monitoring\Polling\PollerSignals;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Public health check (/bug-report contract). 200 when healthy, 503 when degraded.
 */
class HealthController extends Controller
{
    public function __construct(private readonly PollerSignals $pollerSignals) {}

    public function index(): JsonResponse
    {
        $services = [
            'app' => true,
            'database' => $this->check(fn () => DB::connection()->getPdo()),
            'cache' => $this->check(function () {
                Cache::store()->put('health_check', true, 5);

                return Cache::store()->get('health_check') === true;
            }),
            // Redis backs queues, locks and the timer cuts: it is required, not optional.
            'redis' => config('database.redis.client') === null || $this->check(fn () => Redis::connection()->ping()),
            // monitoring:poll leaves a tick every second (RF-13.02).
            'poller' => $this->check(fn () => $this->pollerSignals->pollerAlive(now()->getTimestamp())),
        ];

        $healthy = ! in_array(false, $services, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            return $probe() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
