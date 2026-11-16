<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Reads the daily `errors` log channel (storage/logs/errors-YYYY-MM-DD.log) for `/api/errors` (`/bug-report`).
 * Each entry: timestamp, level, message and the structured context bootstrap/app.php writes.
 */
final class ErrorLogReader
{
    /** @return list<string> paths of every daily file, oldest first */
    public function allFiles(): array
    {
        $files = glob(storage_path('logs/errors-*.log')) ?: [];
        sort($files);

        return $files;
    }

    /** Path of one day's file, or null when that day has none. `$date` must already be a valid Y-m-d. */
    public function fileFor(string $date): ?string
    {
        $file = storage_path("logs/errors-{$date}.log");

        return file_exists($file) ? $file : null;
    }

    /** @return list<string> Y-m-d of the days that have a file */
    public function availableDates(): array
    {
        return array_map(fn (string $file) => $this->dateOf($file), $this->allFiles());
    }

    public function dateOf(string $file): string
    {
        return str_replace('errors-', '', basename($file, '.log'));
    }

    /**
     * @param  list<string>  $files
     * @return list<array<string, mixed>>
     */
    public function entries(array $files, ?string $search = null): array
    {
        $entries = collect($files)
            ->flatMap(fn (string $file) => preg_split('/(?=\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\])/', (string) file_get_contents($file), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $entry) => $this->parse($entry));

        return $this->filter($entries, $search)->values()->all();
    }

    /** @return array<string, mixed> */
    private function parse(string $entry): array
    {
        preg_match('/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]/', $entry, $timestamp);
        preg_match('/\]\s+(\w+)\.(\w+):/', $entry, $level);
        preg_match('/\w+\.\w+:\s(.+?)(?:\s*\{|\s*$)/m', $entry, $message);

        $context = [];
        if (preg_match('/(\{.*\})\s*$/s', $entry, $json)) {
            $decoded = json_decode($json[1], true);
            $context = is_array($decoded) ? $decoded : [];
        }

        $parsed = [
            'timestamp' => $timestamp[1] ?? null,
            'level' => strtoupper($level[2] ?? 'ERROR'),
            'message' => trim($message[1] ?? ''),
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
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function filter(Collection $entries, ?string $search): Collection
    {
        if ($search === null || $search === '') {
            return $entries;
        }

        $search = mb_strtolower($search);

        return $entries->filter(fn (array $entry) => str_contains(mb_strtolower(
            ($entry['message'] ?? '').' '.($entry['action'] ?? '').' '.($entry['url'] ?? '').' '
            .($entry['exception_class'] ?? '').' '.json_encode($entry['input'] ?? []),
        ), $search));
    }
}
