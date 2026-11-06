<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Events\ProjectClosed;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * RF-03.08: closing stops timers (via ProjectClosed); reopening a closed project needs `projects.reopen`.
 */
final class ChangeProjectStatus
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Project $project, ProjectStatus $status): Project
    {
        return DB::transaction(function () use ($actor, $project, $status) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $previous = $project->status;

            if ($previous === $status) {
                return $project;
            }

            if ($previous->isClosed() && ! $status->isClosed() && ! $actor->hasPermission(Permission::ProjectsReopen)) {
                throw new BusinessRuleViolation('Solo un administrador puede reabrir un proyecto finalizado o cancelado.', 'reopen_forbidden', 403);
            }

            $project->forceFill(['status' => $status])->save();

            ($this->recordActivity)('project.status_changed', $project, $actor,
                ['status' => $previous->value], ['status' => $status->value], $project->id);

            if ($status->isClosed() && ! $previous->isClosed()) {
                ProjectClosed::dispatch($project, $actor);
            }

            return $project;
        });
    }
}
