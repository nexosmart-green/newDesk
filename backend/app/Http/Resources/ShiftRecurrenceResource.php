<?php

namespace App\Http\Resources;

use App\Domain\Scheduling\Models\ShiftRecurrence;
use Illuminate\Http\Request;

/**
 * RF-06.03 rule. Eager load `user` and `template`; a deleted template comes as null ("plantilla eliminada").
 *
 * @mixin ShiftRecurrence
 */
class ShiftRecurrenceResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $template = $this->template;

        return [
            'id' => $this->id,
            'user' => ['id' => $this->user->id, 'full_name' => $this->user->fullName()],
            'template' => $template === null || $template->trashed() ? null : [
                'id' => $template->id,
                'name' => $template->name,
                'color' => $template->color,
                'starts_at' => substr($template->starts_at, 0, 5),
                'ends_at' => substr($template->ends_at, 0, 5),
                'mode' => $template->mode->value,
            ],
            'weekdays' => array_map('intval', $this->weekdays),
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'skip_holidays' => $this->skip_holidays,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
