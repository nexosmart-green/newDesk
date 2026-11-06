<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DeleteProject
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor) {
            ($this->recordActivity)('project.deleted', $project, $actor, $project->only(['name', 'code_prefix']));
            $project->delete();
        });
        // Its status page logo (RF-13.07) is a file, not a row the cascade reaches.
        if ($project->logo_path !== null) {
            Storage::disk('local')->delete($project->logo_path);
        }
    }
}
