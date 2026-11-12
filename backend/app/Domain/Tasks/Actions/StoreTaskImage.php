<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\TaskImage;
use App\Domain\Tasks\Support\UploadedFileType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Image pasted or dropped into a task description or comment. The type comes from the file contents (never the
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

    public function __invoke(User $actor, Project $project, UploadedFile $image): TaskImage
    {
        $mime = UploadedFileType::sniff($image);

        if (UploadedFileType::isIphonePhoto($image, $mime)) {
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
