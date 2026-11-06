<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Builds the `{ data, message, errors }` envelope used by every endpoint.
 * For resources prefer `SomeResource::make($model)` — `JsonResource::$wrap` already yields `data`,
 * and AppServiceProvider adds `message`/`errors` to resource responses.
 */
final class ApiResponse
{
    public static function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'message' => $message, 'errors' => null], $status);
    }

    /** @param  array<string, list<string>>|null  $errors */
    public static function error(string $message, int $status, ?array $errors = null, mixed $data = null): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'message' => $message, 'errors' => $errors], $status);
    }
}
