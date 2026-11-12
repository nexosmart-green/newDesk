<?php

namespace App\Http\Resources;

use App\Domain\TimeTracking\Models\HourExtensionRequest;
use Illuminate\Http\Request;

/**
 * RF-05.08. Load `project` and `requester` before using it.
 *
 * @mixin HourExtensionRequest
 */
class HourExtensionRequestResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'message' => $this->message,
            'project' => ['id' => $this->project->id, 'name' => $this->project->name],
            'requested_by' => ['id' => $this->requester->id, 'full_name' => $this->requester->fullName()],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
