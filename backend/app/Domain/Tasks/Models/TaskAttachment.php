<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file attached to a task (RF-04.09). Lives on the private disk and is served only to people who
 * can see the task, through TaskAttachmentController. Hangs from the task, so it travels when the task moves.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $task_id
 * @property int $uploaded_by
 * @property string $original_name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property Carbon|null $created_at
 */
class TaskAttachment extends Model
{
    use BelongsToCompany;

    public const DISK = 'local';

    public const MAX_PER_TASK = 50;

    public const MAX_KILOBYTES = 20 * 1024;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Images and PDFs open in the browser; anything else is downloaded. */
    public function opensInline(): bool
    {
        return $this->isImage() || $this->mime === 'application/pdf';
    }

    public function url(): string
    {
        return "/api/v1/task-attachments/{$this->uuid}";
    }
}
