<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Shared\Activity\RecordActivity;

/**
 * RF-06.01. Editing a template does not change shifts already assigned (they keep their own times).
 */
final class SaveShiftTemplate
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array{name: string, starts_at: string, ends_at: string, color: string, mode: string}  $data */
    public function __invoke(User $actor, array $data, ?ShiftTemplate $template = null): ShiftTemplate
    {
        $before = $template?->only('name', 'starts_at', 'ends_at', 'mode');
        $template ??= new ShiftTemplate(['company_id' => $actor->company_id]);
        $template->fill($data)->save();

        ($this->recordActivity)($before === null ? 'shift_template.created' : 'shift_template.updated', $template, $actor,
            $before, $template->only('name', 'starts_at', 'ends_at', 'mode'));

        return $template;
    }
}
