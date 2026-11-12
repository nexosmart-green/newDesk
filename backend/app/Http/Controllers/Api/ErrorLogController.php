<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ErrorLogReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * `/bug-report` contract: structured production errors, protected by ERRORS_TOKEN (Bearer or `?token=`).
 *
 * GET /api/errors?token=xxx                       today
 * GET /api/errors?token=xxx&date=2026-03-04       one day
 * GET /api/errors?token=xxx&date=all              every day
 * GET /api/errors?token=xxx&search=QueryException
 */
class ErrorLogController extends Controller
{
    private const MAX_FAILED_ATTEMPTS_PER_HOUR = 10;

    public function index(Request $request, ErrorLogReader $reader): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $date = (string) $request->query('date', now()->toDateString());
        $search = $request->query('search');
        $search = is_string($search) ? $search : null;

        if ($date === 'all') {
            $files = $reader->allFiles();
            if ($files === []) {
                return response()->json(['error' => 'No error logs available', 'available_dates' => []], 404);
            }

            return response()->json([
                'date' => $date,
                ...$this->payload($reader->entries($files, $search)),
                'dates_included' => array_map(fn (string $file) => $reader->dateOf($file), $files),
            ]);
        }

        // Only a real day: the value becomes part of a file path.
        $file = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $reader->fileFor($date) : null;
        if ($file === null) {
            return response()->json(['error' => "No logs for date {$date}", 'available_dates' => $reader->availableDates()], 404);
        }

        return response()->json(['date' => $date, ...$this->payload($reader->entries([$file], $search))]);
    }

    /** IP ban after repeated failures, then the token check. Null = let it through. */
    private function guard(Request $request): ?JsonResponse
    {
        $ip = $request->ip();
        if (Cache::has("errors_banned:{$ip}")) {
            return response()->json(['error' => 'Banned'], 403);
        }

        $throttleKey = "errors_attempts:{$ip}";
        $attempts = (int) Cache::get($throttleKey, 0);
        if ($attempts >= self::MAX_FAILED_ATTEMPTS_PER_HOUR) {
            Cache::put("errors_banned:{$ip}", true, now()->addDays(1));
            Log::warning('errors_log.banned', ['ip' => $ip]);

            return response()->json(['error' => 'Banned'], 403);
        }
        Cache::put($throttleKey, $attempts + 1, now()->addHour());

        $token = $request->bearerToken() ?? $request->query('token');
        $expected = (string) config('app.errors_token');
        if (! is_string($token) || $expected === '' || ! hash_equals($expected, $token)) {
            Log::warning('errors_log.unauthorized', ['ip' => $ip]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Cache::forget($throttleKey);

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array{total: int, errors: list<array<string, mixed>>}
     */
    private function payload(array $entries): array
    {
        return ['total' => count($entries), 'errors' => $entries];
    }
}
