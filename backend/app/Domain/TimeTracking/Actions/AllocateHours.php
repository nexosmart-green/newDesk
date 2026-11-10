<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\HourAllocation;
use App\Domain\TimeTracking\Support\HourBag;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.04: load or extend (or correct, with negative seconds) a project's bag. Reason is mandatory.
 * Lifts the block immediately; timers already cut are NOT resumed (R-3).
 */
final class AllocateHours
{
    public function __construct(
        private readonly EvaluateHourAlerts $evaluateAlerts,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, Project $project, int $seconds, string $reason, ?string $reference = null): HourAllocation
    {
        if (! $actor->hasPermission(Permission::HoursAllocate)) {
            throw new BusinessRuleViolation('Tu rol no tiene permiso para cargar horas.', 'cannot_allocate', 403);
        }
        if (! $project->type->hasHourCap()) {
            throw new BusinessRuleViolation("{$project->name} es interno: no tiene bolsa de horas.", 'project_without_bag');
        }
        if ($seconds === 0 || trim($reason) === '') {
            throw new BusinessRuleViolation('Indicá las horas (distintas de cero) y el motivo.', 'allocation_invalid');
        }

        return DB::transaction(function () use ($actor, $project, $seconds, $reason, $reference) {
            $project = Project::query()->forCompany($project->company_id)->lockForUpdate()->findOrFail($project->id);
            $before = ['allocated_seconds' => $project->allocated_seconds];

            $allocation = HourAllocation::query()->create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'seconds' => $seconds,
                'reason' => trim($reason),
                'reference' => $reference !== null ? trim($reference) : null,
                'created_by' => $actor->id,
            ]);

            $project->allocated_seconds += $seconds;
            $project->save();

            ($this->recordActivity)('hours.allocated', $project, $actor, $before, [
                'allocated_seconds' => $project->allocated_seconds,
                'seconds' => $seconds,
                'reason' => $allocation->reason,
                'reference' => $allocation->reference,
            ], $project->id);
            ($this->evaluateAlerts)($project, HourBag::of($project)->consumed());

            return $allocation;
        });
    }
}
