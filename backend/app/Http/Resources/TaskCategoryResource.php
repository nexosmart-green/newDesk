<?php

namespace App\Http\Resources;

use App\Domain\Tasks\Models\TaskCategory;
use Illuminate\Http\Request;

/**
 * @mixin TaskCategory
 */
class TaskCategoryResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tasks_count' => $this->whenCounted('tasks'),
        ];
    }
}
