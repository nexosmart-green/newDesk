<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskAttachment;
use App\Domain\Tasks\Support\AttachmentFileType;
use App\Domain\Tasks\Support\UploadedFileType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * RF-04.09: attaches a file to a task. The type is read from the contents (a renamed HTML is rejected),
 * the file goes to the private disk and is only served through TaskAttachmentController.
 */
final class StoreTaskAttachment
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Task $task, UploadedFile $file): TaskAttachment
    {
        $mime = $this->contentType($file);
        $extension = AttachmentFileType::extensionOf($mime);
        $name = $this->displayName($file, $extension);
        $uuid = (string) Str::uuid();
        $path = $file->storeAs("task-attachments/{$task->company_id}/{$task->id}", "{$uuid}.{$extension}", TaskAttachment::DISK);

        try {
            return DB::transaction(function () use ($actor, $task, $file, $mime, $name, $uuid, $path) {
                Task::query()->forCompany($task->company_id)->whereKey($task->id)->lockForUpdate()->value('id');

                if (TaskAttachment::query()->forCompany($task->company_id)->where('task_id', $task->id)->count() >= TaskAttachment::MAX_PER_TASK) {
                    throw new BusinessRuleViolation(
                        'La tarea ya tiene '.TaskAttachment::MAX_PER_TASK.' archivos, que es el máximo. Borrá alguno antes de subir otro.',
                        'attachment_limit',
                    );
                }

                $attachment = TaskAttachment::query()->create([
                    'uuid' => $uuid,
                    'company_id' => $task->company_id,
                    'task_id' => $task->id,
                    'uploaded_by' => $actor->id,
                    'original_name' => $name,
                    'path' => $path,
                    'mime' => $mime,
                    'size' => (int) $file->getSize(),
                ]);

                ($this->recordActivity)('attachment.added', $task, $actor, after: ['name' => $name], projectId: $task->project_id);

                return $attachment;
            });
        } catch (Throwable $e) {
            Storage::disk(TaskAttachment::DISK)->delete($path);

            throw $e;
        }
    }

    private function contentType(UploadedFile $file): string
    {
        $sniffed = UploadedFileType::sniff($file);

        if (UploadedFileType::isIphonePhoto($file, $sniffed)) {
            throw new BusinessRuleViolation(UploadedFileType::IPHONE_PHOTO_MESSAGE, 'attachment_heic');
        }

        return AttachmentFileType::detect($file, $sniffed) ?? throw new BusinessRuleViolation(
            'Ese tipo de archivo no se puede adjuntar. Subí uno de estos: '.AttachmentFileType::allowedKinds().'.',
            'attachment_type',
        );
    }

    /**
     * The name people see and download: no path, no control or bidi-format characters (a right-to-left
     * override could make `x.zip` read as a PDF), and an extension that matches the contents.
     */
    private function displayName(UploadedFile $file, string $extension): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\\p{Cf}]+/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName())));
        $base = trim(pathinfo($name, PATHINFO_FILENAME));
        $base = Str::limit($base !== '' ? $base : 'archivo', 200, '');

        return "{$base}.{$extension}";
    }
}
