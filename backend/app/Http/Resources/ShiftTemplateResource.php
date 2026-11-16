<?php

namespace App\Http\Resources;

use App\Domain\Scheduling\Models\ShiftTemplate;
use Illuminate\Http\Request;

/** @mixin ShiftTemplate */
class ShiftTemplateResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_at' => substr($this->starts_at, 0, 5),
            'ends_at' => substr($this->ends_at, 0, 5),
            'color' => $this->color,
            'mode' => $this->mode->value,
        ];
    }
}
