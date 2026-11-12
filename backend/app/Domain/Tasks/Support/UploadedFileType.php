<?php

namespace App\Domain\Tasks\Support;

use finfo;
use Illuminate\Http\UploadedFile;

/**
 * What an upload really is, read from its bytes. The name and Content-Type the browser sent are never trusted.
 */
final class UploadedFileType
{
    public const IPHONE_PHOTO_MESSAGE = 'Las fotos HEIC del iPhone no se pueden subir. Convertila a JPG o PNG.';

    private const IPHONE_FORMATS = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];

    public static function sniff(UploadedFile $file): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
    }

    public static function extension(UploadedFile $file): string
    {
        return mb_strtolower($file->getClientOriginalExtension());
    }

    /** iPhone photos (HEIC/HEIF): browsers cannot show them, so people are asked to convert them. */
    public static function isIphonePhoto(UploadedFile $file, string $sniffedMime): bool
    {
        return in_array($sniffedMime, self::IPHONE_FORMATS, true) || in_array(self::extension($file), ['heic', 'heif'], true);
    }
}
