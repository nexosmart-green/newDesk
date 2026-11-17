<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\Period;
use App\Domain\StatusPage\Status\ComponentState;
use App\Domain\StatusPage\Status\Parts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates a project's component, or reconfigures one (RF-13.06, D-039). Each kind keeps only its
 * own fields. Without parts, a new one gets the default for its kind (D-045) and an edit keeps its
 * own. What was measured belonged to the old configuration: an edit starts the state over and closes
 * an open period.
 */
final class SaveComponent
{
    private const STATE_RESET = [
        'status' => ComponentState::UNKNOWN, 'status_since' => null, 'phrase' => '', 'detail' => '', 'bad_streak' => 0,
        'bad_since' => null, 'down_since' => null, 'latency_ms' => null, 'status_code' => null, 'ssl_expires_at' => null,
    ];

    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array<string, mixed>  $input  validated by ComponentRequest */
    public function __invoke(array $input, User $actor, Project $project, ?Component $component = null): Component
    {
        $kind = $input['kind'];
        $web = in_array($kind, [Component::HTTP, Component::HEALTH], true);
        $attributes = [
            'kind' => $kind,
            'label' => $input['label'],
            'position' => $input['position'] ?? 0,
            'url' => $web ? $input['url'] : null,
            'slow_ms' => $web ? ($input['slow_ms'] ?? null) : null,
            'server_id' => $web ? null : $input['server_id'],
            'target' => $web ? null : $input['target'],
            'target_owner' => $kind === Component::PM2 ? ($input['target_owner'] ?? null) : null,
            'max_age_hours' => $kind === Component::TIMER ? ($input['max_age_hours'] ?? null) : null,
            'parts' => $kind === Component::TIMER ? [] : ($input['parts'] ?? null), // backups have their own card
        ];
        if ($attributes['parts'] === null && $component !== null && $component->kind === $kind) {
            $attributes['parts'] = $component->parts;
        }
        if ($attributes['parts'] === null) {
            $siblings = Component::query()->where('project_id', $project->id)
                ->when($component !== null, fn ($query) => $query->whereKeyNot($component->id))->get();
            $attributes['parts'] = Parts::default($kind, $attributes['url'], $siblings);
        }

        return DB::transaction(function () use ($attributes, $actor, $project, $component) {
            $before = $component?->only(array_keys($attributes));
            if ($component === null) {
                $component = Component::query()->create(['company_id' => $project->company_id, 'project_id' => $project->id, ...$attributes]);
            } else {
                $component->fill([...$attributes, ...self::STATE_RESET])->save();
                Period::query()->where('component_id', $component->id)->whereNull('ended_at')->update(['ended_at' => CarbonImmutable::now()->utc()]);
            }
            ($this->recordActivity)(
                $before === null ? 'status_component.created' : 'status_component.updated',
                $component, $actor, $before, $component->only(array_keys($attributes)), $project->id,
            );

            return $component->load('server:id,name');
        });
    }
}
