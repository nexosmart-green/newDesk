<?php

namespace App\Http\Resources;

use App\Domain\Tasks\Models\TaskAttachment;
use App\Domain\Tasks\Policies\TaskAttachmentPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Task attachment (RF-04.09). Eager load `uploader`; only for attachments of a task the viewer sees.
 *
 * @mixin TaskAttachment
 */
class TaskAttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'url' => $this->url(),
            'is_image' => $this->isImage(),
            'uploaded_by' => $this->uploader ? ['id' => $this->uploader->id, 'full_name' => $this->uploader->fullName()] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'can_delete' => TaskAttachmentPolicy::mayDelete($request->user(), $this->resource),
        ];
    }
}
