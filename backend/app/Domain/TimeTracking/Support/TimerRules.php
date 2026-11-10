<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\DayClock;
use App\Domain\TimeTracking\Models\TimeEntry;

/**
 * Guards shared by timers and manual hours. Messages are user-facing and identical for web and MCP (RF-12.07).
 */
final class TimerRules
{
    public function assertCanTrack(User $user, Task $task): void
    {
        if (! $user->hasPermission(Permission::TimeTrack)) {
            throw new BusinessRuleViolation('Tu rol no tiene permiso para cronometrar.', 'cannot_track', 403);
        }

        if (! $task->isAssignedTo($user)) {
            throw new BusinessRuleViolation("Solo podés cargar horas en tareas asignadas a vos ({$task->code} no lo está).", 'task_not_assigned');
        }
    }

    public function assertNoPendingComment(User $user): void
    {
        $pending = TimeEntry::query()->forCompany($user->company_id)
            ->where('user_id', $user->id)->where('comment_pending', true)->with('task')->first();

        if ($pending !== null) {
            throw new BusinessRuleViolation(
                "Tenés un registro de {$pending->task->code} sin comentario. Completalo antes de iniciar otro cronómetro.",
                'comment_pending',
                context: ['time_entry_id' => $pending->id],
            );
        }
    }

    /** RF-03.08 / RF-04 closed kinds. The day split (RF-05.19) checks only this: it may overdraw the bag. */
    public function assertOpen(Project $project, Task $task): void
    {
        if ($project->status->isClosed()) {
            throw new BusinessRuleViolation("{$project->name} está {$project->status->label()}: no admite horas.", 'project_closed');
        }

        if ($task->completed_at !== null || $task->status->isClosed()) {
            throw new BusinessRuleViolation("{$task->code} está cerrada: reabrila para cargarle horas.", 'task_closed');
        }
    }

    /** RF-05.19: a running or not yet split day clock blocks the ▶ (and another day). Call inside the user lock. */
    public function assertNoDayClock(User $user): void
    {
        $day = DayClock::query()->forCompany($user->company_id)->where('user_id', $user->id)->unsplit()->first();
        if ($day === null) {
            return;
        }

        throw $day->isRunning()
            ? new BusinessRuleViolation('Tenés el reloj del día corriendo: frenalo antes de usar el cronómetro de una tarea.', 'day_clock_running', context: ['day_clock_id' => $day->id])
            : new BusinessRuleViolation('Tenés un día sin repartir: repartilo antes de seguir.', 'day_split_pending', context: ['day_clock_id' => $day->id]);
    }

    /** RF-03.08 / RF-04 closed kinds / RF-05.05. Call with the project locked. */
    public function assertTrackable(Project $project, Task $task): void
    {
        $this->assertOpen($project, $task);

        $bag = HourBag::of($project);
        if ($bag->isExhausted()) {
            throw new BusinessRuleViolation(
                "Sin horas disponibles en {$project->name}: pedí una ampliación.",
                'no_hours_available',
                context: ['project_id' => $project->id, 'available_seconds' => $bag->available()],
            );
        }
    }

    public function assertValidComment(?string $comment, string $code = 'comment_required'): void
    {
        if (! TimeEntry::isValidComment($comment)) {
            throw new BusinessRuleViolation(
                'Contá qué hiciste: el comentario es obligatorio (mínimo '.TimeEntry::MIN_COMMENT_LENGTH.' caracteres).',
                $code,
            );
        }
    }
}
