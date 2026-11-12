<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Bell row (RF-09): the notification's own payload (title, message, link…) plus read/created timestamps.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
