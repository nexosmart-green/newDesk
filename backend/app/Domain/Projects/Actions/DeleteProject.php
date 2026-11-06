<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

final class DeleteProject
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor) {
            ($this->recordActivity)('project.deleted', $project, $actor, $project->only(['name', 'code_prefix']));
            $project->delete();
        });
    }
}
