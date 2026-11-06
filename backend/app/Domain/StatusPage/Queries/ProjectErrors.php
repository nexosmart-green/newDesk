<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Text;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Today's errors of a project's own bug-report endpoint (monitoreo fetchErrors, the `/api/errors`
 * contract of docs/error-endpoint-spec.md), read with the token saved on the project. Only what the
 * team needs travels on: never the input, the IP or the user id.
 */
final class ProjectErrors
{
    private const TIMEOUT = 5;

    private const KEEP = 10;

    /**
     * The last 10 of today, newest first, or why they could not be read. Null when the project has
     * no endpoint set up.
     *
     * @return array{error: string|null, items: list<array<string, mixed>>}|null
     */
    public function recent(Project $project): ?array
    {
        if ($project->errors_url === null || $project->getRawOriginal('errors_token') === null) {
            return null;
        }
        $day = $this->today($project);
        if (is_string($day)) {
            return ['error' => $day, 'items' => []];
        }
        $items = $day['items'];
        usort($items, fn (array $a, array $b) => strcmp($b['timestamp'], $a['timestamp']));

        return ['error' => null, 'items' => array_slice($items, 0, self::KEEP)];
    }

    /** today()'s answer when the endpoint turned the token down (its spec bans the IP after 10 an hour). */
    public static function rejected(string $error): bool
    {
        return $error === 'HTTP 401' || $error === 'HTTP 403';
    }

    /**
     * The day's entries, or why they could not be read.
     *
     * @return array{date: string, items: list<array{timestamp: string, level: string, message: string, method: string, url: string, exception_class: string|null}>}|string
     */
    public function today(Project $project): array|string
    {
        try {
            $token = (string) $project->errors_token;
        } catch (DecryptException) {
            return 'no se pudo leer el token guardado';
        }
        try {
            // The endpoint answers JSON: a redirect means a misconfigured URL.
            $response = Http::withToken($token)->acceptJson()->timeout(self::TIMEOUT)->withoutRedirecting()->get((string) $project->errors_url);
        } catch (ConnectionException $e) {
            return 'sin respuesta: '.$e->getMessage();
        }
        if ($response->status() !== 200) {
            return "HTTP {$response->status()}";
        }
        $payload = json_decode($response->body(), true);
        $errors = is_array($payload) ? ($payload['errors'] ?? []) : null;
        if (! is_array($errors) || ! array_is_list($errors)) {
            return 'la respuesta no sigue el formato de /api/errors';
        }

        $text = fn (mixed $value) => is_scalar($value) ? (string) $value : '';
        $items = [];
        foreach ($errors as $entry) {
            $entry = is_array($entry) ? $entry : [];
            $items[] = [
                'timestamp' => $text($entry['timestamp'] ?? ''),
                'level' => $text($entry['level'] ?? ''),
                'message' => Text::clip($text($entry['message'] ?? ''), 300),
                'method' => $text($entry['method'] ?? ''),
                'url' => Text::clip($text($entry['url'] ?? ''), 300),
                'exception_class' => is_scalar($entry['exception_class'] ?? null) ? (string) $entry['exception_class'] : null,
            ];
        }

        return ['date' => $text($payload['date'] ?? ''), 'items' => $items];
    }
}
