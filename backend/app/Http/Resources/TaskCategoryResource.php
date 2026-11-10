<?php

namespace App\Http\Resources;

use App\Domain\Tasks\Models\TaskCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskCategory
 */
class TaskCategoryResource extends JsonResource
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
