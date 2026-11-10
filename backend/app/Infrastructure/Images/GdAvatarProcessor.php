<?php

namespace App\Infrastructure\Images;

use App\Domain\People\Contracts\AvatarProcessor;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use GdImage;

final class GdAvatarProcessor implements AvatarProcessor
{
    private const SUPPORTED = ['image/jpeg', 'image/png', 'image/webp'];

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED, true);
    }

    public function process(string $path): array
    {
        $contents = file_get_contents($path);
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if (! $source instanceof GdImage) {
            throw new BusinessRuleViolation('No pudimos leer la imagen. Probá con JPG, PNG o WebP.', 'avatar_unreadable');
        }

        $side = min(imagesx($source), imagesy($source));
        $offsetX = intdiv(imagesx($source) - $side, 2);
        $offsetY = intdiv(imagesy($source) - $side, 2);

        $encoded = [];
        foreach (self::SIZES as $name => $pixels) {
            $target = imagecreatetruecolor($pixels, $pixels);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $source, 0, 0, $offsetX, $offsetY, $pixels, $pixels, $side, $side);

            ob_start();
            imagewebp($target, null, 85);
            $encoded[$name] = (string) ob_get_clean();
        }

        return $encoded;
    }
}
