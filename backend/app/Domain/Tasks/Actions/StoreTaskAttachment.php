<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskAttachment;
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
    /** Sniffed content type → stored extension. SVG and HTML are left out on purpose: they can carry scripts. */
    private const ALLOWED = [
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

    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Task $task, UploadedFile $file): TaskAttachment
    {
        $mime = $this->contentType($file);
        $name = $this->displayName($file, self::ALLOWED[$mime]);
        $uuid = (string) Str::uuid();
        $path = $file->storeAs("task-attachments/{$task->company_id}/{$task->id}", "{$uuid}.".self::ALLOWED[$mime], TaskAttachment::DISK);

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
            throw new BusinessRuleViolation('Convertí la imagen a JPG o PNG.', 'attachment_heic');
        }

        $extension = UploadedFileType::extension($file);
        $mime = self::REFINED_BY_EXTENSION[$sniffed][$extension] ?? $this->plainTextFallback($sniffed, $extension) ?? $sniffed;
        if (! isset(self::ALLOWED[$mime])) {
            throw new BusinessRuleViolation('Ese tipo de archivo no se puede adjuntar.', 'attachment_type');
        }

        return $mime;
    }

    /**
     * libmagic names text by its look (a log is `text/x-…`, a JSON export `application/json`, an empty file
     * `application/x-empty`). A .txt/.csv with any of those is still plain text; markup that a browser
     * would render (HTML, XML, SVG) stays rejected.
     */
    private function plainTextFallback(string $sniffed, string $extension): ?string
    {
        $isText = str_starts_with($sniffed, 'text/') || in_array($sniffed, ['application/json', 'application/x-empty'], true);
        $isMarkup = in_array($sniffed, ['text/html', 'text/xml', 'application/xml', 'image/svg+xml'], true);

        return match (true) {
            ! $isText || $isMarkup => null,
            $extension === 'txt' => 'text/plain',
            $extension === 'csv' => 'text/csv',
            default => null,
        };
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
