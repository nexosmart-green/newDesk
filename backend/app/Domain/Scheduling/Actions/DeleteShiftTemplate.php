<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.01. Soft delete: shifts already assigned keep their own times and mode.
 */
final class DeleteShiftTemplate
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, ShiftTemplate $template): void
    {
        DB::transaction(function () use ($actor, $template) {
            ($this->recordActivity)('shift_template.deleted', $template, $actor, before: $template->only('name', 'starts_at', 'ends_at', 'mode'));
            $template->delete();
        });
    }
}
