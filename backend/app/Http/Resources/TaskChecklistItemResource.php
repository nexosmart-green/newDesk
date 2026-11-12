<?php

namespace App\Http\Resources;

use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Checklist line. Eager load `doneBy`.
 *
 * @mixin TaskChecklistItem
 */
class TaskChecklistItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'position' => $this->position,
            'done' => $this->isDone(),
            'done_at' => $this->done_at?->toIso8601String(),
            'done_by' => $this->doneBy ? ['id' => $this->doneBy->id, 'full_name' => $this->doneBy->fullName()] : null,
        ];
    }
}
