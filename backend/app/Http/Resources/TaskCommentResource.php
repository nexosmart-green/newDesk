<?php

namespace App\Http\Resources;

use App\Domain\Tasks\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskComment
 */
class TaskCommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => ['id' => $this->author->id, 'full_name' => $this->author->fullName()],
            'mentions' => $this->mentions->map(fn ($user) => ['id' => $user->id, 'full_name' => $user->fullName()])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
