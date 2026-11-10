<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\TaskImage;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Image pasted or dropped into a task description. The type comes from the file contents (never the
 * name the browser sent) and it is stored on the private disk, served only through TaskImageController.
 */
final class StoreTaskImage
{
    /** Content type → stored extension. SVG is left out on purpose: it can carry scripts. */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const IPHONE_FORMATS = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];

    public function __invoke(User $actor, Project $project, UploadedFile $image): TaskImage
    {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($image->getRealPath());
        $isIphoneFormat = in_array($mime, self::IPHONE_FORMATS, true)
            || in_array(mb_strtolower($image->getClientOriginalExtension()), ['heic', 'heif'], true);

        if ($isIphoneFormat) {
            throw new BusinessRuleViolation('Convertí la imagen a JPG o PNG.', 'task_image_heic');
        }
        if (! isset(self::ALLOWED[$mime])) {
            throw new BusinessRuleViolation('Formato no soportado. Subí una imagen JPG, PNG, WebP o GIF.', 'task_image_format');
        }

        $uuid = (string) Str::uuid();
        $path = $image->storeAs("task-images/{$project->company_id}/{$project->id}", "{$uuid}.".self::ALLOWED[$mime], TaskImage::DISK);

        return TaskImage::query()->create([
            'uuid' => $uuid,
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'uploaded_by' => $actor->id,
            'path' => $path,
            'mime' => $mime,
            'size' => (int) $image->getSize(),
        ]);
    }
}
