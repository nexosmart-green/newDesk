<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\StatusPage\Models\Component;
use Illuminate\Support\Facades\DB;

/** Its checks, periods, notes and PageSpeed runs go with it (FK cascade). */
final class DeleteComponent
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(Component $component, User $actor): void
    {
        DB::transaction(function () use ($component, $actor) {
            ($this->recordActivity)('status_component.deleted', $component, $actor, $component->only(['kind', 'label']), null, $component->project_id);
            $component->delete();
        });
    }
}
