<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;

/**
 * RF-04.12: the hours of a task follow it to another project — they stop consuming the origin bag and
 * start consuming the destination one. All or nothing (CA-04.12b, like R-4). The caller holds the locks
 * of BOTH projects and runs this inside its transaction.
 *
 * @return int seconds moved
 */
final class TransferTaskHours
{
    public function __construct(private readonly EvaluateHourAlerts $evaluateAlerts) {}

    public function __invoke(Task $task, Project $from, Project $to): int
    {
        if (TimeEntry::query()->forCompany($task->company_id)->where('task_id', $task->id)->running()->exists()) {
            throw new BusinessRuleViolation("{$task->code} tiene un cronómetro corriendo: detenelo antes de moverla.", 'task_timer_running');
        }

        $seconds = (int) TimeEntry::query()->forCompany($task->company_id)->where('task_id', $task->id)->sum('seconds');

        $destination = HourBag::of($to);
        if ($seconds > 0 && $destination->hasCap() && $seconds > $destination->available()) {
            throw new BusinessRuleViolation(
                "{$to->name} no tiene horas suficientes para recibir las de {$task->code}. Cargá una ampliación primero.",
                'move_exceeds_bag',
                context: ['needed_seconds' => $seconds, 'available_seconds' => $destination->available()],
            );
        }

        // Soft-deleted entries move too, so a later restore stays in the right project.
        TimeEntry::query()->forCompany($task->company_id)->withTrashed()->where('task_id', $task->id)->update(['project_id' => $to->id]);

        if ($seconds > 0) {
            $from->consumed_seconds -= $seconds;
            $from->save();
            $to->consumed_seconds += $seconds;
            $to->save();

            ($this->evaluateAlerts)($from, HourBag::of($from)->consumed());
            ($this->evaluateAlerts)($to, HourBag::of($to)->consumed());
        }

        return $seconds;
    }
}
