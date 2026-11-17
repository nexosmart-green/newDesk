<?php

namespace App\Domain\Support;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A report screenshot or chat image (D-041, D-049): PNG, JPEG or WebP judged by its bytes, never by
 * its name or declared type. The files live on the private disk under reports/YYYY/MM/ and only the
 * API serves them.
 */
final readonly class ReportImage
{
    public const DISK = 'local';

    public const MAX_BYTES = 5 << 20;

    public const EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(public string $bytes, public string $contentType) {}

    /** The three image signatures of Go's http.DetectContentType the portal accepts; null for anything else. */
    public static function sniff(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 6) === 'WEBPVP' => 'image/webp',
            default => null,
        };
    }

    public function size(): int
    {
        return strlen($this->bytes);
    }

    /** Writes it under reports/YYYY/MM/<random>.<ext> and returns that relative path. */
    public function store(CarbonImmutable $now): string
    {
        $path = sprintf('reports/%s/%s.%s', $now->format('Y/m'), bin2hex(random_bytes(16)), self::EXTENSIONS[$this->contentType]);
        Storage::disk(self::DISK)->put($path, $this->bytes);

        return $path;
    }

    public static function response(?string $path, ?string $contentType, string $missing): Response
    {
        $disk = Storage::disk(self::DISK);
        if ($path === null || $contentType === null || ! $disk->exists($path)) {
            throw new BusinessRuleViolation($missing, 'not_found', 404);
        }

        return response((string) $disk->get($path), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
