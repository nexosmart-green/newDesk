<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ErrorLogController extends Controller
{
    /**
     * Fetch structured error logs.
     *
     * GET /api/errors?token=xxx
     * GET /api/errors?token=xxx&date=2026-03-04
     * GET /api/errors?token=xxx&date=all
     * GET /api/errors?token=xxx&search=QueryException
     *
     * Protected by ERRORS_TOKEN env variable.
     */
    public function index(Request $request): JsonResponse
    {
        $ip = $request->ip();

        // Check IP ban
        if (Cache::has("errors_banned:{$ip}")) {
            return response()->json(['error' => 'Banned'], 403);
        }

        // Throttle: max 10 failed attempts per hour
        $throttleKey = "errors_attempts:{$ip}";
        $attempts = (int) Cache::get($throttleKey, 0);

        if ($attempts >= 10) {
            Cache::put("errors_banned:{$ip}", true, now()->addDays(1));
            Log::warning('errors_log.banned', ['ip' => $ip]);

            return response()->json(['error' => 'Banned'], 403);
        }

        Cache::put($throttleKey, $attempts + 1, now()->addHour());

        // Validate token via Bearer header or query param
        $token = $request->bearerToken() ?? $request->query('token');

        $expected = (string) config('app.errors_token');
        if (! is_string($token) || $expected === '' || ! hash_equals($expected, $token)) {
            Log::warning('errors_log.unauthorized', ['ip' => $ip]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Auth OK — reset throttle counter
        Cache::forget($throttleKey);

        $date = $request->query('date', now()->toDateString());
        $search = $request->query('search');

        // Resolve log files
        if ($date === 'all') {
            $logFiles = glob(storage_path('logs/errors-*.log'));

            if (empty($logFiles)) {
                return response()->json([
                    'error' => 'No error logs available',
                    'available_dates' => [],
                ], 404);
            }

            sort($logFiles);
        } else {
            $logFile = storage_path("logs/errors-{$date}.log");

            if (! file_exists($logFile)) {
                $available = collect(glob(storage_path('logs/errors-*.log')))
                    ->map(fn ($f) => basename($f, '.log'))
                    ->map(fn ($name) => str_replace('errors-', '', $name))
                    ->sort()
                    ->values()
                    ->all();

                return response()->json([
                    'error' => "No logs for date {$date}",
                    'available_dates' => $available,
                ], 404);
            }

            $logFiles = [$logFile];
        }

        // Parse all log entries
        $allEntries = [];
        foreach ($logFiles as $file) {
            $content = file_get_contents($file);
            $entries = preg_split('/(?=\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\])/', $content, -1, PREG_SPLIT_NO_EMPTY);
            array_push($allEntries, ...$entries);
        }

        $parsed = collect($allEntries)->map(function ($entry) {
            // Extract timestamp
            preg_match('/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]/', $entry, $tsMatch);
            $timestamp = $tsMatch[1] ?? null;

            // Extract level
            preg_match('/\]\s+(\w+)\.(\w+):/', $entry, $levelMatch);
            $level = strtoupper($levelMatch[2] ?? 'ERROR');

            // Extract message (first line after level:)
            preg_match('/\w+\.\w+:\s(.+?)(?:\s*\{|\s*$)/m', $entry, $msgMatch);
            $message = trim($msgMatch[1] ?? '');

            // Extract JSON context
            $context = [];
            if (preg_match('/(\{.*\})\s*$/s', $entry, $jsonMatch)) {
                $decoded = json_decode($jsonMatch[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $decoded;
                }
            }

            $parsed = [
                'timestamp' => $timestamp,
                'level' => $level,
                'message' => $message,
                'action' => $context['action'] ?? null,
                'method' => $context['method'] ?? null,
                'url' => $context['url'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'ip' => $context['ip'] ?? null,
                'input' => $context['input'] ?? null,
                'exception_class' => $context['exception_class'] ?? null,
                'file' => $context['file'] ?? null,
                'trace_short' => $context['trace_short'] ?? null,
            ];

            if (isset($context['validation_errors'])) {
                $parsed['validation_errors'] = $context['validation_errors'];
            }

            return $parsed;
        });

        // Apply search filter
        if ($search) {
            $search = mb_strtolower($search);
            $parsed = $parsed->filter(function ($entry) use ($search) {
                $haystack = mb_strtolower(
                    ($entry['message'] ?? '').' '.
                    ($entry['action'] ?? '').' '.
                    ($entry['url'] ?? '').' '.
                    ($entry['exception_class'] ?? '').' '.
                    json_encode($entry['input'] ?? [])
                );

                return str_contains($haystack, $search);
            });
        }

        $entries = $parsed->values()->all();

        $response = [
            'date' => $date,
            'total' => count($entries),
            'errors' => $entries,
        ];

        if ($date === 'all') {
            $response['dates_included'] = collect($logFiles)
                ->map(fn ($f) => str_replace('errors-', '', basename($f, '.log')))
                ->values()
                ->all();
        }

        return response()->json($response);
    }
}
