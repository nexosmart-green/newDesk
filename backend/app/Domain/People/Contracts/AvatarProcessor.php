<?php

namespace App\Domain\People\Contracts;

/**
 * Turns an uploaded picture into the square sizes the app uses (RF-01.05).
 * Implementations live in app/Infrastructure (GD today; Imagick could add HEIC).
 */
interface AvatarProcessor
{
    /** Avatar (header, lists) and profile sizes, in pixels. */
    public const SIZES = ['avatar' => 64, 'profile' => 256];

    public function supports(string $mimeType): bool;

    /**
     * Center-crops to a square and encodes each size.
     *
     * @return array<string, string> size name => binary WebP
     */
    public function process(string $path): array;
}
