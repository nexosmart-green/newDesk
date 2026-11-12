<?php

namespace App\Domain\Tasks\Support;

use Illuminate\Http\UploadedFile;

/**
 * Which files may be attached to a task (RF-04.09) and the precise type each one is stored as.
 * Built on UploadedFileType: the bytes decide, the client's name only refines among what the bytes allow.
 */
final class AttachmentFileType
{
    /** Content type → stored extension. SVG and HTML are left out on purpose: they can carry scripts. */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
    ];

    /**
     * Office files are zip containers and libmagic often reports them as plain zip; text files carry no
     * signature. For those the extension picks the precise type among the ones the bytes allow.
     */
    private const REFINED_BY_EXTENSION = [
        'application/zip' => [
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        ],
        'text/plain' => ['csv' => 'text/csv'],
        'application/csv' => ['csv' => 'text/csv'],
    ];

    /** The allowed content type the upload is stored as, or null when it may not be attached. */
    public static function detect(UploadedFile $file, string $sniffedMime): ?string
    {
        $extension = UploadedFileType::extension($file);
        $mime = self::REFINED_BY_EXTENSION[$sniffedMime][$extension]
            ?? self::plainTextFallback($sniffedMime, $extension)
            ?? $sniffedMime;

        return isset(self::ALLOWED[$mime]) ? $mime : null;
    }

    public static function extensionOf(string $allowedMime): string
    {
        return self::ALLOWED[$allowedMime];
    }

    /** "JPG, PNG, … o ZIP", straight from the allowlist so the message never drifts from it. */
    public static function allowedKinds(): string
    {
        $kinds = array_map('mb_strtoupper', array_values(self::ALLOWED));
        $last = array_pop($kinds);

        return implode(', ', $kinds).' o '.$last;
    }

    /**
     * libmagic names text by its look (a log is `text/x-…`, a JSON export `application/json`, an empty file
     * `application/x-empty`). A .txt/.csv with any of those is still plain text; markup that a browser
     * would render (HTML, XML, SVG) stays rejected.
     */
    private static function plainTextFallback(string $sniffedMime, string $extension): ?string
    {
        $isText = str_starts_with($sniffedMime, 'text/') || in_array($sniffedMime, ['application/json', 'application/x-empty'], true);
        $isMarkup = in_array($sniffedMime, ['text/html', 'text/xml', 'application/xml', 'image/svg+xml'], true);

        return match (true) {
            ! $isText || $isMarkup => null,
            $extension === 'txt' => 'text/plain',
            $extension === 'csv' => 'text/csv',
            default => null,
        };
    }
}
