<?php

namespace App\Http\Resources;

use App\Domain\Assistant\Models\AssistantFile;
use Illuminate\Http\Request;

/** @mixin AssistantFile */
class AssistantFileResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'format' => $this->format,
            'size' => $this->size,
            'url' => '/api/v1/assistant/files/'.$this->uuid,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
