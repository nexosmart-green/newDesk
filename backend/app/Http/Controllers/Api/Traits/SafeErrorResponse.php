<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Trait for controllers to return safe JSON error responses.
 *
 * In debug mode: shows real exception message.
 * In production: shows generic fallback message.
 * Always logs the full error to the 'errors' channel.
 */
trait SafeErrorResponse
{
    protected function safeError(
        \Throwable $e,
        string $fallback = 'Internal server error',
        int $status = 500
    ): JsonResponse {
        $request = request();
        $route = $request->route();

        Log::channel('errors')->error($fallback, [
            'exception_class' => get_class($e),
            'exception' => $e->getMessage(),
            'action' => $route ? $route->getActionName() : 'unknown',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route_name' => $route?->getName(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            // Add tenant/org context if multi-tenant:
            // 'tenant_id' => $route?->parameter('tenant')?->id ?? $request->input('tenant_id'),
            'input' => $request->except([
                'password', 'password_confirmation', 'token', 'secret',
            ]),
            'file' => $e->getFile().':'.$e->getLine(),
            'trace_short' => collect(array_slice($e->getTrace(), 0, 5))
                ->map(fn ($frame) => ($frame['class'] ?? '')
                    .($frame['type'] ?? '')
                    .($frame['function'] ?? '')
                    .' ('.basename($frame['file'] ?? '').':'.($frame['line'] ?? '?').')')
                ->all(),
        ]);

        return response()->json([
            'error' => config('app.debug') ? $e->getMessage() : $fallback,
        ], $status);
    }
}
