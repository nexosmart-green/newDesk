<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\TaskAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RF-04.09: removes an attachment and its file. The file goes only after the row is gone for good,
 * so a failed transaction never leaves a row pointing at nothing.
 */
final class DeleteTaskAttachment
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, TaskAttachment $attachment): void
    {
        DB::transaction(function () use ($actor, $attachment) {
            $task = $attachment->loadMissing('task')->task;
            $attachment->delete();

            ($this->recordActivity)('attachment.removed', $task, $actor, before: ['name' => $attachment->original_name], projectId: $task->project_id);
        });

        Storage::disk(TaskAttachment::DISK)->delete($attachment->path);
    }
}
