<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use App\Domain\TimeTracking\Support\ManualEntryWindow;
use App\Domain\TimeTracking\Support\TimerRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.15: Admin corrects anyone's entries; a person corrects their own within the window.
 * Growing an entry must fit in the bag (R-4); every change is audited with before/after (RNF-07).
 * Passing $startedAt/$endedAt null keeps the times and only changes the comment.
 */
final class CorrectTimeEntry
{
    public function __construct(
        private readonly TimerRules $rules,
        private readonly ManualEntryWindow $window,
        private readonly EvaluateHourAlerts $evaluateAlerts,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, TimeEntry $entry, ?Carbon $startedAt, ?Carbon $endedAt, string $comment): TimeEntry
    {
        if ($entry->isRunning()) {
            throw new BusinessRuleViolation('Detené el cronómetro antes de corregir el registro.', 'entry_running');
        }
        $this->rules->assertValidComment($comment);

        $startedAt ??= $entry->started_at;
        $endedAt ??= $entry->ended_at;
        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw new BusinessRuleViolation('La hora «Hasta» tiene que ser posterior a «Desde».', 'invalid_range');
        }
        if ($endedAt->isFuture()) {
            throw new BusinessRuleViolation('No se pueden cargar horas futuras: elegí una hora «Hasta» que ya haya pasado.', 'invalid_range');
        }
        // Both the original and the new start must be inside the window: old entries cannot be moved into it.
        $this->window->assertAllows($actor, $entry->started_at);
        $this->window->assertAllows($actor, $startedAt);

        return DB::transaction(function () use ($actor, $entry, $startedAt, $endedAt, $comment) {
            $project = Project::query()->forCompany($entry->company_id)->lockForUpdate()->findOrFail($entry->project_id);
            $entry = TimeEntry::query()->forCompany($entry->company_id)->lockForUpdate()->findOrFail($entry->id);

            $before = $this->snapshot($entry);
            $seconds = (int) $startedAt->diffInSeconds($endedAt, true);
            $delta = $seconds - (int) $entry->seconds;

            $bag = HourBag::of($project);
            if ($delta > 0 && $bag->hasCap() && $delta > $bag->available()) {
                throw new BusinessRuleViolation("La corrección supera lo disponible en {$project->name}. Pedí una ampliación o acortá el registro.", 'manual_exceeds_bag');
            }

            $entry->forceFill([
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'seconds' => $seconds,
                'comment' => trim($comment),
                'comment_pending' => false,
            ])->save();

            $project->consumed_seconds += $delta;
            $project->save();

            ($this->recordActivity)('time.corrected', $entry, $actor, $before, $this->snapshot($entry), $project->id);
            ($this->evaluateAlerts)($project, HourBag::of($project)->consumed());

            return $entry;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(TimeEntry $entry): array
    {
        return [
            'started_at' => $entry->started_at->toIso8601String(),
            'ended_at' => $entry->ended_at?->toIso8601String(),
            'seconds' => $entry->seconds,
            'comment' => $entry->comment,
        ];
    }
}
