<?php

namespace App\Http\Resources;

use App\Domain\Shared\Activity\ActivityLog;
use Illuminate\Http\Request;

/**
 * @mixin ActivityLog
 */
class ActivityResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'actor' => $this->actor ? ['id' => $this->actor->id, 'full_name' => $this->actor->fullName()] : null,
            'source' => $this->source->value,
            'token_name' => $this->token_name,
            'before' => $this->before,
            'after' => $this->after,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
