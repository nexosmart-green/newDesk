<?php

namespace App\Http\Resources;

use App\Domain\Tasks\Models\TaskLabel;
use Illuminate\Http\Request;

/**
 * @mixin TaskLabel
 */
class TaskLabelResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'tasks_count' => $this->whenCounted('tasks'),
        ];
    }
}
