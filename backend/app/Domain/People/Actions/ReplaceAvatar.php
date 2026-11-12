<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Contracts\AvatarProcessor;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * RF-01.05: square avatar stored in two sizes. Files are versioned by a random name so caches never serve the old picture.
 */
final class ReplaceAvatar
{
    public const DISK = 'public';

    public function __construct(private readonly AvatarProcessor $processor) {}

    public function __invoke(User $user, UploadedFile $picture): User
    {
        if (! $this->processor->supports((string) $picture->getMimeType())) {
            throw new BusinessRuleViolation(
                'Ese formato no sirve para la foto. Subí JPG, PNG o WebP (en iPhone, elegí "Más compatible" en Cámara → Formatos).',
                'avatar_format',
            );
        }

        $directory = "avatars/{$user->id}/".Str::random(12);
        foreach ($this->processor->process($picture->getRealPath()) as $size => $binary) {
            Storage::disk(self::DISK)->put("{$directory}/{$size}.webp", $binary);
        }

        $previous = $user->avatar_path;
        $user->forceFill(['avatar_path' => $directory])->save();
        if ($previous !== null) {
            Storage::disk(self::DISK)->deleteDirectory($previous);
        }

        return $user;
    }

    /** @return array<string, string>|null size name => public URL */
    public static function urls(?string $avatarPath): ?array
    {
        if ($avatarPath === null) {
            return null;
        }

        return array_map(
            fn (string $size) => Storage::disk(self::DISK)->url("{$avatarPath}/{$size}.webp"),
            array_combine(array_keys(AvatarProcessor::SIZES), array_keys(AvatarProcessor::SIZES)),
        );
    }
}
